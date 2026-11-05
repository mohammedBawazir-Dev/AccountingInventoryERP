/* ============================================================================
   AccountingInventoryERP_DB - schema repair and completion script - corrected version 3
   Target: SQL Server 2022
   Fixes: invoice_date is added before use; legacy TEXT columns are not altered
          because dependent default constraints can block ALTER COLUMN.

   IMPORTANT:
   - Run this script against the EMPTY database created for this ERP project.
   - The script intentionally rebuilds dbo.Stocks and dbo.Suppliers to correct
     their IDENTITY definitions. It refuses to do so if either table has rows.
   - Existing table/column names are retained as they currently exist in SQL Server.
   - AccountType and the two voucher tables are additions needed to support
     requirements mentioned in the supplied screen specification.
   - Take a database backup before running any schema-changing script.
   ============================================================================ */

USE [AccountingInventoryERP_DB];
SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'AccountingInventoryERP_DB'
    THROW 51000, N'Wrong database. Select AccountingInventoryERP_DB before running this script.', 1;

IF OBJECT_ID(N'dbo.Stocks', N'U') IS NULL
   OR OBJECT_ID(N'dbo.Suppliers', N'U') IS NULL
   OR OBJECT_ID(N'dbo.CharAccount', N'U') IS NULL
   OR OBJECT_ID(N'dbo.Coins', N'U') IS NULL
    THROW 51001, N'One or more required existing tables are missing. The script was stopped.', 1;

/* The two tables below must be empty because their IDENTITY columns are being corrected. */
IF EXISTS (SELECT 1 FROM dbo.Stocks)
    THROW 51002, N'dbo.Stocks is not empty. The script stopped to protect existing data.', 1;

IF EXISTS (SELECT 1 FROM dbo.Suppliers)
    THROW 51003, N'dbo.Suppliers is not empty. The script stopped to protect existing data.', 1;

/* These source tables are empty according to the project setup confirmation.
   A date cannot be invented for already existing sales invoices. */
IF EXISTS (SELECT 1 FROM dbo.sales_invoices)
    AND COL_LENGTH(N'dbo.sales_invoices', N'invoice_date') IS NULL
    THROW 51004, N'sales_invoices contains rows and invoice_date is missing. Backfill real invoice dates before running this script.', 1;

BEGIN TRY
    BEGIN TRANSACTION;

    /* ======================================================================
       1. Correct dbo.Stocks
       StockID must be IDENTITY; AccountID must be a normal FK, not IDENTITY.
       ====================================================================== */

    IF OBJECT_ID(N'dbo.FK_purchaseInvoices_Stocks', N'F') IS NOT NULL
        ALTER TABLE dbo.purchaseInvoices DROP CONSTRAINT FK_purchaseInvoices_Stocks;

    IF OBJECT_ID(N'dbo.FK_inventory_movement_details_Stocks', N'F') IS NOT NULL
        ALTER TABLE dbo.inventory_movement_details DROP CONSTRAINT FK_inventory_movement_details_Stocks;

    IF OBJECT_ID(N'dbo.FK_inventory_movements_Stocks', N'F') IS NOT NULL
        ALTER TABLE dbo.inventory_movements DROP CONSTRAINT FK_inventory_movements_Stocks;

    IF OBJECT_ID(N'dbo.FK_sales_invoice_details_Stocks', N'F') IS NOT NULL
        ALTER TABLE dbo.sales_invoice_details DROP CONSTRAINT FK_sales_invoice_details_Stocks;

    IF OBJECT_ID(N'dbo.FK_Stocks_CharAccount', N'F') IS NOT NULL
        ALTER TABLE dbo.Stocks DROP CONSTRAINT FK_Stocks_CharAccount;

    DROP TABLE dbo.Stocks;

    CREATE TABLE dbo.Stocks
    (
        StockID   INT IDENTITY(1,1) NOT NULL,
        StockName NVARCHAR(255) NOT NULL,
        AccountID INT NOT NULL,
        is_active BIT NOT NULL
            CONSTRAINT DF_Stocks_is_active DEFAULT (1),
        CONSTRAINT PK_Stocks PRIMARY KEY CLUSTERED (StockID),
        CONSTRAINT FK_Stocks_CharAccount
            FOREIGN KEY (AccountID) REFERENCES dbo.CharAccount(AccountID)
    );

    ALTER TABLE dbo.purchaseInvoices WITH CHECK
        ADD CONSTRAINT FK_purchaseInvoices_Stocks
        FOREIGN KEY (warehouse_id) REFERENCES dbo.Stocks(StockID);

    ALTER TABLE dbo.inventory_movement_details WITH CHECK
        ADD CONSTRAINT FK_inventory_movement_details_Stocks
        FOREIGN KEY (warehouse_id) REFERENCES dbo.Stocks(StockID);

    ALTER TABLE dbo.inventory_movements WITH CHECK
        ADD CONSTRAINT FK_inventory_movements_Stocks
        FOREIGN KEY (warehouse_id) REFERENCES dbo.Stocks(StockID);

    ALTER TABLE dbo.sales_invoice_details WITH CHECK
        ADD CONSTRAINT FK_sales_invoice_details_Stocks
        FOREIGN KEY (warehouse_id) REFERENCES dbo.Stocks(StockID);

    /* ======================================================================
       2. Correct dbo.Suppliers
       SuplierID must be generated automatically. Phone numbers are text,
       not arithmetic values, so leading zeroes and + can be retained.
       ====================================================================== */

    IF OBJECT_ID(N'dbo.FK_PaymentVouchers_Suppliers', N'F') IS NOT NULL
        ALTER TABLE dbo.PaymentVouchers DROP CONSTRAINT FK_PaymentVouchers_Suppliers;

    IF OBJECT_ID(N'dbo.FK_Suppliers_CharAccount', N'F') IS NOT NULL
        ALTER TABLE dbo.Suppliers DROP CONSTRAINT FK_Suppliers_CharAccount;

    DROP TABLE dbo.Suppliers;

    CREATE TABLE dbo.Suppliers
    (
        SuplierID     INT IDENTITY(1,1) NOT NULL,
        AccountID     INT NULL,
        SuplierName   NVARCHAR(255) NOT NULL,
        SuplierPhone  NVARCHAR(20) NULL,
        SuplierArea   NVARCHAR(255) NULL,
        SuplierStoped BIT NOT NULL
            CONSTRAINT DF_Suppliers_SuplierStoped DEFAULT (0),
        CONSTRAINT PK_Suppliers PRIMARY KEY CLUSTERED (SuplierID),
        CONSTRAINT FK_Suppliers_CharAccount
            FOREIGN KEY (AccountID) REFERENCES dbo.CharAccount(AccountID)
    );

    /* ======================================================================
       3. Add AccountType, which is referenced by the data dictionary note
          for CharAccount.AccTypeID but was absent from the actual database.
       ====================================================================== */

    IF OBJECT_ID(N'dbo.AccountType', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.AccountType
        (
            AccountTypeID INT IDENTITY(1,1) NOT NULL,
            TypeName      NVARCHAR(100) NOT NULL,
            IsActive      BIT NOT NULL
                CONSTRAINT DF_AccountType_IsActive DEFAULT (1),
            CONSTRAINT PK_AccountType PRIMARY KEY CLUSTERED (AccountTypeID),
            CONSTRAINT UQ_AccountType_TypeName UNIQUE (TypeName)
        );
    END;

    /* Seed the four account categories named in the data dictionary.
       Existing matching rows are not duplicated. */
    IF NOT EXISTS (SELECT 1 FROM dbo.AccountType WHERE TypeName = N'Assets')
        INSERT INTO dbo.AccountType (TypeName) VALUES (N'Assets');
    IF NOT EXISTS (SELECT 1 FROM dbo.AccountType WHERE TypeName = N'Liabilities')
        INSERT INTO dbo.AccountType (TypeName) VALUES (N'Liabilities');
    IF NOT EXISTS (SELECT 1 FROM dbo.AccountType WHERE TypeName = N'Expenses')
        INSERT INTO dbo.AccountType (TypeName) VALUES (N'Expenses');
    IF NOT EXISTS (SELECT 1 FROM dbo.AccountType WHERE TypeName = N'Revenues')
        INSERT INTO dbo.AccountType (TypeName) VALUES (N'Revenues');

    IF NOT EXISTS
    (
        SELECT 1 FROM sys.foreign_keys
        WHERE name = N'FK_CharAccount_AccountType'
          AND parent_object_id = OBJECT_ID(N'dbo.CharAccount')
    )
    BEGIN
        ALTER TABLE dbo.CharAccount WITH CHECK
            ADD CONSTRAINT FK_CharAccount_AccountType
            FOREIGN KEY (AccTypeID) REFERENCES dbo.AccountType(AccountTypeID);
    END;

    /* ======================================================================
       4. Missing fields identified by comparing the data dictionary/screens
          with the actual SQL Server schema.
       ====================================================================== */

    IF COL_LENGTH(N'dbo.Coins', N'is_active') IS NULL
    BEGIN
        ALTER TABLE dbo.Coins
            ADD is_active BIT NOT NULL
                CONSTRAINT DF_Coins_is_active DEFAULT (1);
    END;

    IF COL_LENGTH(N'dbo.sales_invoices', N'invoice_date') IS NULL
    BEGIN
        ALTER TABLE dbo.sales_invoices ADD invoice_date DATE NULL;

        /* Execute separately so SQL Server compiles the new column reference
           only after the ALTER TABLE ADD has completed. */
        EXEC sys.sp_executesql N'
            UPDATE dbo.sales_invoices
            SET invoice_date = CONVERT(DATE, GETDATE())
            WHERE invoice_date IS NULL;
        ';

        EXEC sys.sp_executesql N'
            ALTER TABLE dbo.sales_invoices
            ALTER COLUMN invoice_date DATE NOT NULL;
        ';
    END;

    /* Make customer/item names and customer address large enough for normal
       business data. Column names are not changed. */
    ALTER TABLE dbo.Item ALTER COLUMN ItemName NVARCHAR(255) NOT NULL;
    ALTER TABLE dbo.Customers ALTER COLUMN CusAddress NVARCHAR(500) NULL;

    /* Legacy TEXT columns are intentionally left unchanged here.
       SQL Server cannot ALTER a TEXT column while a default constraint
       depends on it. Preserve the existing columns to avoid that failure.
       They can be migrated separately after inspecting and dropping any
       dependent constraints, if Unicode MAX conversion is required. */

    /* Preserve existing timestamp columns and their default constraints.
       Their data types are not altered in this repair script because SQL
       Server blocks ALTER COLUMN while a default constraint depends on it.
       PHP can work with the existing DATE columns; a separate migration can
       be planned after inspecting and safely handling each dependent object. */

    /* Automatic creation timestamps. updated_at is set by application code
       whenever a record is modified. */
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.inventory_movements') AND c.name = N'created_at')
        ALTER TABLE dbo.inventory_movements ADD CONSTRAINT DF_inventory_movements_created_at DEFAULT (SYSDATETIME()) FOR created_at;
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.inventory_movement_details') AND c.name = N'created_at')
        ALTER TABLE dbo.inventory_movement_details ADD CONSTRAINT DF_inventory_movement_details_created_at DEFAULT (SYSDATETIME()) FOR created_at;
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.purchaseInvoices') AND c.name = N'created_at')
        ALTER TABLE dbo.purchaseInvoices ADD CONSTRAINT DF_purchaseInvoices_created_at DEFAULT (SYSDATETIME()) FOR created_at;
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.purchase_invoice_details') AND c.name = N'created_at')
        ALTER TABLE dbo.purchase_invoice_details ADD CONSTRAINT DF_purchase_invoice_details_created_at DEFAULT (SYSDATETIME()) FOR created_at;
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.sales_invoices') AND c.name = N'created_at')
        ALTER TABLE dbo.sales_invoices ADD CONSTRAINT DF_sales_invoices_created_at DEFAULT (SYSDATETIME()) FOR created_at;
    IF NOT EXISTS (SELECT 1 FROM sys.default_constraints dc JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id WHERE dc.parent_object_id = OBJECT_ID(N'dbo.sales_invoice_details') AND c.name = N'created_at')
        ALTER TABLE dbo.sales_invoice_details ADD CONSTRAINT DF_sales_invoice_details_created_at DEFAULT (SYSDATETIME()) FOR created_at;

    /* ======================================================================
       5. Restore the document-detail cascade behavior described in the
          data dictionary. Other accounting FKs remain NO_ACTION.
       ====================================================================== */

    IF OBJECT_ID(N'dbo.FK_purchase_invoice_details_purchaseInvoices', N'F') IS NOT NULL
        ALTER TABLE dbo.purchase_invoice_details
            DROP CONSTRAINT FK_purchase_invoice_details_purchaseInvoices;

    ALTER TABLE dbo.purchase_invoice_details WITH CHECK
        ADD CONSTRAINT FK_purchase_invoice_details_purchaseInvoices
        FOREIGN KEY (purchase_invoice_id)
        REFERENCES dbo.purchaseInvoices(purchase_invoice_id)
        ON DELETE CASCADE;

    IF OBJECT_ID(N'dbo.FK_sales_invoice_details_sales_invoices', N'F') IS NOT NULL
        ALTER TABLE dbo.sales_invoice_details
            DROP CONSTRAINT FK_sales_invoice_details_sales_invoices;

    ALTER TABLE dbo.sales_invoice_details WITH CHECK
        ADD CONSTRAINT FK_sales_invoice_details_sales_invoices
        FOREIGN KEY (sales_invoice_id)
        REFERENCES dbo.sales_invoices(sales_invoice_id)
        ON DELETE CASCADE;

    /* This relationship was missing from the actual database. */
    IF OBJECT_ID(N'dbo.FK_inventory_movement_details_inventory_movements', N'F') IS NOT NULL
        ALTER TABLE dbo.inventory_movement_details
            DROP CONSTRAINT FK_inventory_movement_details_inventory_movements;

    ALTER TABLE dbo.inventory_movement_details WITH CHECK
        ADD CONSTRAINT FK_inventory_movement_details_inventory_movements
        FOREIGN KEY (movement_id)
        REFERENCES dbo.inventory_movements(movement_id)
        ON DELETE CASCADE;

    /* ======================================================================
       6. UNIQUE indexes required by the data dictionary.
       ====================================================================== */

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.Coins') AND name = N'UX_Coins_CoinCode')
        CREATE UNIQUE INDEX UX_Coins_CoinCode ON dbo.Coins(CoinCode);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'UX_CharAccount_AccCode')
        CREATE UNIQUE INDEX UX_CharAccount_AccCode ON dbo.CharAccount(AccCode);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'UX_CharAccount_SystemKey')
        CREATE UNIQUE INDEX UX_CharAccount_SystemKey
            ON dbo.CharAccount(SystemKey)
            WHERE SystemKey IS NOT NULL AND SystemKey <> N'';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.purchaseInvoices') AND name = N'UX_purchaseInvoices_invoice_number')
        CREATE UNIQUE INDEX UX_purchaseInvoices_invoice_number ON dbo.purchaseInvoices(invoice_number);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.sales_invoices') AND name = N'UX_sales_invoices_invoice_number')
        CREATE UNIQUE INDEX UX_sales_invoices_invoice_number ON dbo.sales_invoices(invoice_number);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.Journal_Entries') AND name = N'UX_Journal_Entries_entryNo')
        CREATE UNIQUE INDEX UX_Journal_Entries_entryNo ON dbo.Journal_Entries(entryNo);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'UX_inventory_movements_display_id')
        CREATE UNIQUE INDEX UX_inventory_movements_display_id ON dbo.inventory_movements(display_id);

    /* Only one currency may be designated as the system/base currency. */
    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.Coins') AND name = N'UX_Coins_OneSystemCurrency')
        CREATE UNIQUE INDEX UX_Coins_OneSystemCurrency
            ON dbo.Coins(CoinSystem)
            WHERE CoinSystem = 1;

    /* ======================================================================
       7. CHECK constraints for values explicitly constrained by the
          dictionary/screens, plus basic non-negative financial quantities.
       ====================================================================== */

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_AccLevel')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_AccLevel CHECK (AccLevel BETWEEN 1 AND 5);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_Nature')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_Nature CHECK (Nature IN (1, 2));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_IsPostable')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_IsPostable CHECK (IsPostable IN (0, 1));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_Is_active')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_Is_active CHECK (Is_active IN (0, 1));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_is_System')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_is_System CHECK (is_System IN (0, 1));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.CharAccount') AND name = N'CK_CharAccount_NoSelfParent')
        ALTER TABLE dbo.CharAccount WITH CHECK ADD CONSTRAINT CK_CharAccount_NoSelfParent CHECK (AccParent IS NULL OR AccParent <> AccountID);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.Coins') AND name = N'CK_Coins_ExchangeRate_Positive')
        ALTER TABLE dbo.Coins WITH CHECK ADD CONSTRAINT CK_Coins_ExchangeRate_Positive CHECK (CoinsExchangeRate IS NULL OR CoinsExchangeRate > 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.OpeningBalances') AND name = N'CK_OpeningBalances_ExchangeRate_Positive')
        ALTER TABLE dbo.OpeningBalances WITH CHECK ADD CONSTRAINT CK_OpeningBalances_ExchangeRate_Positive CHECK (OPExchangeRate IS NULL OR OPExchangeRate > 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.OpeningBalances') AND name = N'CK_OpeningBalances_NonNegativeAmounts')
        ALTER TABLE dbo.OpeningBalances WITH CHECK ADD CONSTRAINT CK_OpeningBalances_NonNegativeAmounts CHECK ((OPDebit IS NULL OR OPDebit >= 0) AND (OPCredit IS NULL OR OPCredit >= 0));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.JournalEntrryLine') AND name = N'CK_JournalEntrryLine_ExchangeRate_Positive')
        ALTER TABLE dbo.JournalEntrryLine WITH CHECK ADD CONSTRAINT CK_JournalEntrryLine_ExchangeRate_Positive CHECK (ExchangeRate IS NULL OR ExchangeRate > 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.JournalEntrryLine') AND name = N'CK_JournalEntrryLine_NonNegativeAmounts')
        ALTER TABLE dbo.JournalEntrryLine WITH CHECK ADD CONSTRAINT CK_JournalEntrryLine_NonNegativeAmounts CHECK
        (
            (Debit IS NULL OR Debit >= 0) AND
            (credit IS NULL OR credit >= 0) AND
            (localdebit IS NULL OR localdebit >= 0) AND
            (localcredit IS NULL OR localcredit >= 0)
        );

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.Journal_Entries') AND name = N'CK_Journal_Entries_TotalAmount_NonNegative')
        ALTER TABLE dbo.Journal_Entries WITH CHECK ADD CONSTRAINT CK_Journal_Entries_TotalAmount_NonNegative CHECK (totalAmount IS NULL OR totalAmount >= 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.purchaseInvoices') AND name = N'CK_purchaseInvoices_PaymentMethod')
        ALTER TABLE dbo.purchaseInvoices WITH CHECK ADD CONSTRAINT CK_purchaseInvoices_PaymentMethod CHECK (payment_method IS NULL OR payment_method IN (1, 2, 3, 4));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.purchaseInvoices') AND name = N'CK_purchaseInvoices_ExchangeRate_Positive')
        ALTER TABLE dbo.purchaseInvoices WITH CHECK ADD CONSTRAINT CK_purchaseInvoices_ExchangeRate_Positive CHECK (exchange_rate IS NULL OR exchange_rate > 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.purchase_invoice_details') AND name = N'CK_purchase_invoice_details_NonNegativeValues')
        ALTER TABLE dbo.purchase_invoice_details WITH CHECK ADD CONSTRAINT CK_purchase_invoice_details_NonNegativeValues CHECK
        (
            (quantity IS NULL OR quantity >= 0) AND
            (price IS NULL OR price >= 0) AND
            (discount IS NULL OR discount >= 0) AND
            (total IS NULL OR total >= 0)
        );

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.sales_invoices') AND name = N'CK_sales_invoices_PaymentMethod')
        ALTER TABLE dbo.sales_invoices WITH CHECK ADD CONSTRAINT CK_sales_invoices_PaymentMethod CHECK (payment_method IS NULL OR payment_method IN (1, 2, 3, 4));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.sales_invoices') AND name = N'CK_sales_invoices_ExchangeRate_Positive')
        ALTER TABLE dbo.sales_invoices WITH CHECK ADD CONSTRAINT CK_sales_invoices_ExchangeRate_Positive CHECK (exchange_rate IS NULL OR exchange_rate > 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.sales_invoice_details') AND name = N'CK_sales_invoice_details_NonNegativeValues')
        ALTER TABLE dbo.sales_invoice_details WITH CHECK ADD CONSTRAINT CK_sales_invoice_details_NonNegativeValues CHECK
        (
            (quantity IS NULL OR quantity >= 0) AND
            (price IS NULL OR price >= 0) AND
            (cost_price IS NULL OR cost_price >= 0) AND
            (discount IS NULL OR discount >= 0) AND
            (total IS NULL OR total >= 0)
        );

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'CK_inventory_movements_Direction')
        ALTER TABLE dbo.inventory_movements WITH CHECK ADD CONSTRAINT CK_inventory_movements_Direction CHECK (direction IN (N'in', N'out'));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'CK_inventory_movements_MovementType')
        ALTER TABLE dbo.inventory_movements WITH CHECK ADD CONSTRAINT CK_inventory_movements_MovementType CHECK
        (movement_type IN (N'supply', N'issue', N'purchase', N'sale', N'purchase_return', N'sale_return'));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'CK_inventory_movements_SourceType')
        ALTER TABLE dbo.inventory_movements WITH CHECK ADD CONSTRAINT CK_inventory_movements_SourceType CHECK
        (source_type IS NULL OR source_type IN (N'purchase_invoice', N'sales_invoice'));

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'CK_inventory_movements_Total_NonNegative')
        ALTER TABLE dbo.inventory_movements WITH CHECK ADD CONSTRAINT CK_inventory_movements_Total_NonNegative CHECK (total IS NULL OR total >= 0);

    IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(N'dbo.inventory_movement_details') AND name = N'CK_inventory_movement_details_NonNegativeValues')
        ALTER TABLE dbo.inventory_movement_details WITH CHECK ADD CONSTRAINT CK_inventory_movement_details_NonNegativeValues CHECK
        (
            (quantity IS NULL OR quantity >= 0) AND
            (unit_cost IS NULL OR unit_cost >= 0) AND
            (min_price IS NULL OR min_price >= 0) AND
            (max_price IS NULL OR max_price >= 0) AND
            (sale_price IS NULL OR sale_price >= 0) AND
            (total IS NULL OR total >= 0) AND
            (min_price IS NULL OR max_price IS NULL OR min_price <= max_price)
        );

    /* ======================================================================
       8. Voucher tables.
          These are additional tables proposed from the receipt/payment screen
          specification; their detailed columns were not enumerated in the
          original 19-table data dictionary.
       ====================================================================== */

    IF OBJECT_ID(N'dbo.ReceiptVouchers', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.ReceiptVouchers
        (
            ReceiptVoucherID  INT IDENTITY(1,1) NOT NULL,
            VoucherNumber     NVARCHAR(50) NOT NULL,
            VoucherDate       DATE NOT NULL,
            CustomerID        INT NOT NULL,
            CoinID            INT NOT NULL,
            ExchangeRate      DECIMAL(18,6) NOT NULL
                CONSTRAINT DF_ReceiptVouchers_ExchangeRate DEFAULT (1),
            Amount            DECIMAL(18,2) NOT NULL,
            PaymentMethod     TINYINT NOT NULL,
            PaymentAccountID  INT NOT NULL,
            BoxID             INT NULL,
            BankID            INT NULL,
            CheckNumber       NVARCHAR(50) NULL,
            Statement         NVARCHAR(MAX) NULL,
            Reference         NVARCHAR(100) NULL,
            JournalEntryID    INT NULL,
            CreatedAt         DATETIME2(0) NOT NULL
                CONSTRAINT DF_ReceiptVouchers_CreatedAt DEFAULT (SYSDATETIME()),
            UpdatedAt         DATETIME2(0) NULL,
            CONSTRAINT PK_ReceiptVouchers PRIMARY KEY CLUSTERED (ReceiptVoucherID),
            CONSTRAINT FK_ReceiptVouchers_Customers FOREIGN KEY (CustomerID) REFERENCES dbo.Customers(CustomersID),
            CONSTRAINT FK_ReceiptVouchers_Coins FOREIGN KEY (CoinID) REFERENCES dbo.Coins(CoinID),
            CONSTRAINT FK_ReceiptVouchers_CharAccount FOREIGN KEY (PaymentAccountID) REFERENCES dbo.CharAccount(AccountID),
            CONSTRAINT FK_ReceiptVouchers_Boxes FOREIGN KEY (BoxID) REFERENCES dbo.Boxes(BoxID),
            CONSTRAINT FK_ReceiptVouchers_Banks FOREIGN KEY (BankID) REFERENCES dbo.Banks(BankID),
            CONSTRAINT FK_ReceiptVouchers_JournalEntries FOREIGN KEY (JournalEntryID) REFERENCES dbo.Journal_Entries(entryID),
            CONSTRAINT CK_ReceiptVouchers_Amount CHECK (Amount > 0),
            CONSTRAINT CK_ReceiptVouchers_ExchangeRate CHECK (ExchangeRate > 0),
            CONSTRAINT CK_ReceiptVouchers_PaymentMethod CHECK (PaymentMethod IN (1, 2, 3, 4))
        );

        CREATE UNIQUE INDEX UX_ReceiptVouchers_VoucherNumber ON dbo.ReceiptVouchers(VoucherNumber);
        CREATE UNIQUE INDEX UX_ReceiptVouchers_JournalEntryID ON dbo.ReceiptVouchers(JournalEntryID) WHERE JournalEntryID IS NOT NULL;
    END;

    IF OBJECT_ID(N'dbo.PaymentVouchers', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.PaymentVouchers
        (
            PaymentVoucherID  INT IDENTITY(1,1) NOT NULL,
            VoucherNumber     NVARCHAR(50) NOT NULL,
            VoucherDate       DATE NOT NULL,
            SuplierID         INT NOT NULL,
            CoinID            INT NOT NULL,
            ExchangeRate      DECIMAL(18,6) NOT NULL
                CONSTRAINT DF_PaymentVouchers_ExchangeRate DEFAULT (1),
            Amount            DECIMAL(18,2) NOT NULL,
            PaymentMethod     TINYINT NOT NULL,
            PaymentAccountID  INT NOT NULL,
            BoxID             INT NULL,
            BankID            INT NULL,
            CheckNumber       NVARCHAR(50) NULL,
            Statement         NVARCHAR(MAX) NULL,
            Reference         NVARCHAR(100) NULL,
            JournalEntryID    INT NULL,
            CreatedAt         DATETIME2(0) NOT NULL
                CONSTRAINT DF_PaymentVouchers_CreatedAt DEFAULT (SYSDATETIME()),
            UpdatedAt         DATETIME2(0) NULL,
            CONSTRAINT PK_PaymentVouchers PRIMARY KEY CLUSTERED (PaymentVoucherID),
            CONSTRAINT FK_PaymentVouchers_Suppliers FOREIGN KEY (SuplierID) REFERENCES dbo.Suppliers(SuplierID),
            CONSTRAINT FK_PaymentVouchers_Coins FOREIGN KEY (CoinID) REFERENCES dbo.Coins(CoinID),
            CONSTRAINT FK_PaymentVouchers_CharAccount FOREIGN KEY (PaymentAccountID) REFERENCES dbo.CharAccount(AccountID),
            CONSTRAINT FK_PaymentVouchers_Boxes FOREIGN KEY (BoxID) REFERENCES dbo.Boxes(BoxID),
            CONSTRAINT FK_PaymentVouchers_Banks FOREIGN KEY (BankID) REFERENCES dbo.Banks(BankID),
            CONSTRAINT FK_PaymentVouchers_JournalEntries FOREIGN KEY (JournalEntryID) REFERENCES dbo.Journal_Entries(entryID),
            CONSTRAINT CK_PaymentVouchers_Amount CHECK (Amount > 0),
            CONSTRAINT CK_PaymentVouchers_ExchangeRate CHECK (ExchangeRate > 0),
            CONSTRAINT CK_PaymentVouchers_PaymentMethod CHECK (PaymentMethod IN (1, 2, 3, 4))
        );

        CREATE UNIQUE INDEX UX_PaymentVouchers_VoucherNumber ON dbo.PaymentVouchers(VoucherNumber);
        CREATE UNIQUE INDEX UX_PaymentVouchers_JournalEntryID ON dbo.PaymentVouchers(JournalEntryID) WHERE JournalEntryID IS NOT NULL;
    END;

    /* Re-add the supplier FK if this script is run again after initial setup. */
    IF OBJECT_ID(N'dbo.FK_PaymentVouchers_Suppliers', N'F') IS NULL
        ALTER TABLE dbo.PaymentVouchers WITH CHECK
            ADD CONSTRAINT FK_PaymentVouchers_Suppliers
            FOREIGN KEY (SuplierID) REFERENCES dbo.Suppliers(SuplierID);

    /* ======================================================================
       9. Helpful indexes for common list/detail lookups and date filters.
       ====================================================================== */

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.JournalEntrryLine') AND name = N'IX_JournalEntrryLine_entryID')
        CREATE INDEX IX_JournalEntrryLine_entryID ON dbo.JournalEntrryLine(entryID);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.purchase_invoice_details') AND name = N'IX_purchase_invoice_details_invoice_id')
        CREATE INDEX IX_purchase_invoice_details_invoice_id ON dbo.purchase_invoice_details(purchase_invoice_id);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.sales_invoice_details') AND name = N'IX_sales_invoice_details_invoice_id')
        CREATE INDEX IX_sales_invoice_details_invoice_id ON dbo.sales_invoice_details(sales_invoice_id);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.inventory_movement_details') AND name = N'IX_inventory_movement_details_movement_id')
        CREATE INDEX IX_inventory_movement_details_movement_id ON dbo.inventory_movement_details(movement_id);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.purchaseInvoices') AND name = N'IX_purchaseInvoices_invoice_date')
        CREATE INDEX IX_purchaseInvoices_invoice_date ON dbo.purchaseInvoices(invoice_date);

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.sales_invoices') AND name = N'IX_sales_invoices_invoice_date')
        EXEC sys.sp_executesql N'CREATE INDEX IX_sales_invoices_invoice_date ON dbo.sales_invoices(invoice_date);';

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.inventory_movements') AND name = N'IX_inventory_movements_date_type_warehouse')
        CREATE INDEX IX_inventory_movements_date_type_warehouse
            ON dbo.inventory_movements(movement_date, movement_type, warehouse_id);

    COMMIT TRANSACTION;

    PRINT N'SUCCESS: AccountingInventoryERP_DB schema repair completed.';
    PRINT N'Stocks and Suppliers were rebuilt with correct IDENTITY columns.';
    PRINT N'AccountType, missing fields, relationships, indexes, and checks were added.';
    PRINT N'ReceiptVouchers and PaymentVouchers were added as screen-support tables.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
    DECLARE @ErrorNumber INT = ERROR_NUMBER();
    DECLARE @ErrorLine INT = ERROR_LINE();

    PRINT N'ERROR: Schema repair failed. The transaction was rolled back.';
    PRINT N'Error number: ' + CONVERT(NVARCHAR(20), @ErrorNumber);
    PRINT N'Error line: ' + CONVERT(NVARCHAR(20), @ErrorLine);
    PRINT @ErrorMessage;

    THROW;
END CATCH;

/* ============================================================================
   10. Post-run verification queries (read-only)
   ============================================================================ */

SELECT
    t.name AS TableName,
    c.name AS ColumnName,
    TYPE_NAME(c.user_type_id) AS DataType,
    c.is_identity AS IsIdentity,
    c.is_nullable AS IsNullable
FROM sys.tables t
INNER JOIN sys.columns c ON c.object_id = t.object_id
WHERE t.schema_id = SCHEMA_ID(N'dbo')
  AND t.name IN (N'Stocks', N'Suppliers', N'sales_invoices', N'Coins', N'AccountType', N'ReceiptVouchers', N'PaymentVouchers')
ORDER BY t.name, c.column_id;

SELECT
    fk.name AS ForeignKeyName,
    OBJECT_NAME(fk.parent_object_id) AS ParentTable,
    OBJECT_NAME(fk.referenced_object_id) AS ReferencedTable,
    fk.delete_referential_action_desc AS OnDelete
FROM sys.foreign_keys fk
WHERE fk.parent_object_id IN
(
    OBJECT_ID(N'dbo.Stocks'),
    OBJECT_ID(N'dbo.Suppliers'),
    OBJECT_ID(N'dbo.inventory_movement_details'),
    OBJECT_ID(N'dbo.purchase_invoice_details'),
    OBJECT_ID(N'dbo.sales_invoice_details'),
    OBJECT_ID(N'dbo.ReceiptVouchers'),
    OBJECT_ID(N'dbo.PaymentVouchers')
)
ORDER BY ParentTable, ForeignKeyName;

SELECT
    t.name AS TableName,
    cc.name AS CheckConstraintName,
    cc.definition AS CheckDefinition,
    cc.is_disabled AS IsDisabled
FROM sys.check_constraints cc
INNER JOIN sys.tables t ON t.object_id = cc.parent_object_id
WHERE t.schema_id = SCHEMA_ID(N'dbo')
ORDER BY t.name, cc.name;
