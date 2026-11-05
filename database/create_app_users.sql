/* Optional fallback if the PHP application cannot create its own security tables. */
IF OBJECT_ID(N'dbo.AppUsers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppUsers
    (
        UserID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppUsers PRIMARY KEY,
        FullName NVARCHAR(150) NOT NULL,
        Email NVARCHAR(254) NOT NULL,
        PasswordHash NVARCHAR(255) NOT NULL,
        RoleName NVARCHAR(30) NOT NULL CONSTRAINT DF_AppUsers_RoleName DEFAULT N'custom',
        IsActive BIT NOT NULL CONSTRAINT DF_AppUsers_IsActive DEFAULT (1),
        IsSuperAdmin BIT NOT NULL CONSTRAINT DF_AppUsers_IsSuperAdmin DEFAULT (0),
        MustChangePassword BIT NOT NULL CONSTRAINT DF_AppUsers_MustChangePassword DEFAULT (1),
        AuthVersion INT NOT NULL CONSTRAINT DF_AppUsers_AuthVersion DEFAULT (2),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_AppUsers_CreatedAt DEFAULT SYSDATETIME(),
        LastLoginAt DATETIME2(0) NULL
    );
    CREATE UNIQUE INDEX UX_AppUsers_Email ON dbo.AppUsers(Email);
END;
GO

IF COL_LENGTH(N'dbo.AppUsers', N'IsSuperAdmin') IS NULL
    ALTER TABLE dbo.AppUsers ADD IsSuperAdmin BIT NOT NULL CONSTRAINT DF_AppUsers_IsSuperAdmin_Migration DEFAULT (0) WITH VALUES;
IF COL_LENGTH(N'dbo.AppUsers', N'MustChangePassword') IS NULL
    ALTER TABLE dbo.AppUsers ADD MustChangePassword BIT NOT NULL CONSTRAINT DF_AppUsers_MustChangePassword_Migration DEFAULT (1) WITH VALUES;
IF COL_LENGTH(N'dbo.AppUsers', N'AuthVersion') IS NULL
    ALTER TABLE dbo.AppUsers ADD AuthVersion INT NOT NULL CONSTRAINT DF_AppUsers_AuthVersion_Migration DEFAULT (0) WITH VALUES;
GO

IF OBJECT_ID(N'dbo.AppUserPermissions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppUserPermissions
    (
        UserID INT NOT NULL,
        ModuleKey NVARCHAR(100) NOT NULL,
        CanView BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanView DEFAULT (0),
        CanCreate BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanCreate DEFAULT (0),
        CanEdit BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanEdit DEFAULT (0),
        CanDelete BIT NOT NULL CONSTRAINT DF_AppUserPermissions_CanDelete DEFAULT (0),
        CONSTRAINT PK_AppUserPermissions PRIMARY KEY (UserID, ModuleKey),
        CONSTRAINT FK_AppUserPermissions_AppUsers FOREIGN KEY (UserID)
            REFERENCES dbo.AppUsers(UserID) ON DELETE CASCADE
    );
END;
GO
