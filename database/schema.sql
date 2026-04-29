-- ══════════════════════════════════════════════════════════
--  0. Enable Mixed Mode Auth & Create Database
-- ══════════════════════════════════════════════════════════
USE master;
GO

EXEC xp_instance_regwrite 
    N'HKEY_LOCAL_MACHINE', 
    N'Software\Microsoft\MSSQLServer\MSSQLServer', 
    N'LoginMode', REG_DWORD, 2;
GO

IF DB_ID('ProjectEngineDB') IS NULL
    CREATE DATABASE ProjectEngineDB;
GO

-- ══════════════════════════════════════════════════════════
--  1. Create App Login & Grant Access
-- ══════════════════════════════════════════════════════════
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = 'ProjectEngineUser')
BEGIN
    CREATE LOGIN ProjectEngineUser 
        WITH PASSWORD = 'Engine@2026!', 
        DEFAULT_DATABASE = ProjectEngineDB;
END
GO

USE ProjectEngineDB;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'ProjectEngineUser')
BEGIN
    CREATE USER ProjectEngineUser FOR LOGIN ProjectEngineUser;
    ALTER ROLE db_owner ADD MEMBER ProjectEngineUser;
END
GO

-- ──────────────────────────────────────────────
-- 0.  DROP ALL FOREIGN KEY CONSTRAINTS FIRST
-- ──────────────────────────────────────────────
DECLARE @sql NVARCHAR(MAX) = N'';

SELECT @sql += 'ALTER TABLE ' + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + '.' + QUOTENAME(OBJECT_NAME(parent_object_id)) + 
               ' DROP CONSTRAINT ' + QUOTENAME(name) + ';' + CHAR(13)
FROM sys.foreign_keys;

EXEC sp_executesql @sql;
GO

-- ──────────────────────────────────────────────
-- 1.  DROP ALL TABLES (Now safe to drop in any order)
-- ──────────────────────────────────────────────
IF OBJECT_ID('dbo.Workspace_Tasks',      'U') IS NOT NULL DROP TABLE dbo.Workspace_Tasks;
IF OBJECT_ID('dbo.Workspace_Messages',   'U') IS NOT NULL DROP TABLE dbo.Workspace_Messages;
IF OBJECT_ID('dbo.Project_Applications', 'U') IS NOT NULL DROP TABLE dbo.Project_Applications;
IF OBJECT_ID('dbo.Projects',             'U') IS NOT NULL DROP TABLE dbo.Projects;
IF OBJECT_ID('dbo.Developer_Skills',     'U') IS NOT NULL DROP TABLE dbo.Developer_Skills;
IF OBJECT_ID('dbo.Skills',               'U') IS NOT NULL DROP TABLE dbo.Skills;
IF OBJECT_ID('dbo.Developers',           'U') IS NOT NULL DROP TABLE dbo.Developers;
IF OBJECT_ID('dbo.Clients',              'U') IS NOT NULL DROP TABLE dbo.Clients;
IF OBJECT_ID('dbo.Users',                'U') IS NOT NULL DROP TABLE dbo.Users;
GO

-- ──────────────────────────────────────────────
-- 2.  RECREATE TABLE: Users
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Users (
    user_id         INT             IDENTITY(1,1)   NOT NULL,
    email           NVARCHAR(255)                    NOT NULL,
    password_hash   NVARCHAR(255)                    NOT NULL,
    role            NVARCHAR(20)                     NOT NULL,
    created_at      DATETIME        DEFAULT GETDATE() NOT NULL,

    CONSTRAINT PK_Users             PRIMARY KEY (user_id),
    CONSTRAINT UQ_Users_Email       UNIQUE (email),
    CONSTRAINT CK_Users_Role        CHECK (role IN ('Client', 'Developer'))
);
GO

-- ──────────────────────────────────────────────
-- 3.  RECREATE TABLE: Clients
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Clients (
    client_id       INT             NOT NULL,
    company_name    NVARCHAR(200)   NOT NULL,
    contact_number  NVARCHAR(30)    NULL,

    CONSTRAINT PK_Clients           PRIMARY KEY (client_id),
    CONSTRAINT FK_Clients_Users     FOREIGN KEY (client_id)
        REFERENCES dbo.Users (user_id)
        ON DELETE CASCADE
);
GO

-- ──────────────────────────────────────────────
-- 4.  RECREATE TABLE: Developers
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Developers (
    dev_id          INT             NOT NULL,
    full_name       NVARCHAR(150)   NOT NULL,
    level           NVARCHAR(20)    NOT NULL,
    hourly_rate     DECIMAL(10,2)   DEFAULT 0.00    NOT NULL,
    portfolio_url   NVARCHAR(500)   NULL,
    job_title       NVARCHAR(100)   NULL,
    github_url      NVARCHAR(255)   NULL,
    linkedin_url    NVARCHAR(255)   NULL,
    bio             NVARCHAR(MAX)   NULL,
    is_booked       BIT             DEFAULT 0       NOT NULL,

    CONSTRAINT PK_Developers        PRIMARY KEY (dev_id),
    CONSTRAINT FK_Developers_Users  FOREIGN KEY (dev_id)
        REFERENCES dbo.Users (user_id)
        ON DELETE CASCADE,
    CONSTRAINT CK_Developers_Level  CHECK (level IN ('Trainee', 'Junior', 'Mid', 'Senior'))
);
GO

-- ──────────────────────────────────────────────
-- 5.  RECREATE TABLE: Skills
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Skills (
    skill_id        INT             IDENTITY(1,1)   NOT NULL,
    skill_name      NVARCHAR(100)                    NOT NULL,

    CONSTRAINT PK_Skills            PRIMARY KEY (skill_id),
    CONSTRAINT UQ_Skills_Name       UNIQUE (skill_name)
);
GO

-- ──────────────────────────────────────────────
-- 6.  RECREATE TABLE: Developer_Skills
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Developer_Skills (
    dev_id          INT             NOT NULL,
    skill_id        INT             NOT NULL,

    CONSTRAINT PK_DevSkills         PRIMARY KEY (dev_id, skill_id),
    CONSTRAINT FK_DevSkills_Dev     FOREIGN KEY (dev_id)
        REFERENCES dbo.Developers (dev_id) ON DELETE CASCADE,
    CONSTRAINT FK_DevSkills_Skill   FOREIGN KEY (skill_id)
        REFERENCES dbo.Skills (skill_id) ON DELETE CASCADE
);
GO

-- ──────────────────────────────────────────────
-- 7.  RECREATE TABLE: Projects
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Projects (
    project_id      INT             IDENTITY(1,1)   NOT NULL,
    client_id       INT                              NOT NULL,
    title           NVARCHAR(300)                    NOT NULL,
    description     NVARCHAR(MAX)                    NULL,
    budget_tier     NVARCHAR(50)                     NOT NULL,
    required_level  NVARCHAR(20)                     NOT NULL,
    status          NVARCHAR(20)    DEFAULT 'Pending' NOT NULL,

    CONSTRAINT PK_Projects          PRIMARY KEY (project_id),
    CONSTRAINT FK_Projects_Client   FOREIGN KEY (client_id)
        REFERENCES dbo.Clients (client_id) ON DELETE CASCADE,
    CONSTRAINT CK_Projects_Status   CHECK (status IN ('Pending', 'Active', 'Completed')),
    CONSTRAINT CK_Projects_Level    CHECK (required_level IN ('Trainee', 'Junior', 'Mid', 'Senior'))
);
GO

-- ──────────────────────────────────────────────
-- 8.  RECREATE TABLE: Project_Applications
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Project_Applications (
    application_id  INT             IDENTITY(1,1)   NOT NULL,
    project_id      INT                              NOT NULL,
    dev_id          INT                              NOT NULL,
    status          NVARCHAR(20)    DEFAULT 'Applied' NOT NULL,
    applied_at      DATETIME        DEFAULT GETDATE() NOT NULL,

    CONSTRAINT PK_ProjectApps       PRIMARY KEY (application_id),
    CONSTRAINT FK_ProjApps_Project  FOREIGN KEY (project_id)
        REFERENCES dbo.Projects (project_id) ON DELETE CASCADE,
    CONSTRAINT FK_ProjApps_Dev      FOREIGN KEY (dev_id)
        REFERENCES dbo.Developers (dev_id) ON DELETE NO ACTION,
    CONSTRAINT CK_ProjApps_Status   CHECK (status IN ('Applied', 'Accepted', 'Rejected')),
    CONSTRAINT UQ_ProjApps_Unique   UNIQUE (project_id, dev_id)
);
GO

-- ──────────────────────────────────────────────
-- 9.  RECREATE TABLE: Workspace_Messages
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Workspace_Messages (
    message_id      INT             IDENTITY(1,1)   NOT NULL,
    project_id      INT                              NOT NULL,
    sender_user_id  INT                              NOT NULL,
    message_body    NVARCHAR(MAX)                    NOT NULL,
    sent_at         DATETIME        DEFAULT GETDATE() NOT NULL,

    CONSTRAINT PK_WorkspaceMsgs     PRIMARY KEY (message_id),
    CONSTRAINT FK_WkMsg_Project     FOREIGN KEY (project_id)
        REFERENCES dbo.Projects (project_id) ON DELETE CASCADE,
    CONSTRAINT FK_WkMsg_Sender      FOREIGN KEY (sender_user_id)
        REFERENCES dbo.Users (user_id) ON DELETE NO ACTION
);
GO

-- ──────────────────────────────────────────────
-- 10. RECREATE TABLE: Workspace_Tasks
-- ──────────────────────────────────────────────
CREATE TABLE dbo.Workspace_Tasks (
    task_id         INT             IDENTITY(1,1)   NOT NULL,
    project_id      INT                              NOT NULL,
    title           NVARCHAR(200)                    NOT NULL,
    description     NVARCHAR(MAX)                    NULL,
    status          NVARCHAR(20)    DEFAULT 'To Do'  NOT NULL,
    created_at      DATETIME        DEFAULT GETDATE() NOT NULL,
    updated_at      DATETIME        DEFAULT GETDATE() NOT NULL,

    CONSTRAINT PK_WorkspaceTasks    PRIMARY KEY (task_id),
    CONSTRAINT FK_WkTask_Project    FOREIGN KEY (project_id)
        REFERENCES dbo.Projects (project_id) ON DELETE CASCADE,
    CONSTRAINT CK_WkTask_Status     CHECK (status IN ('To Do', 'In Progress', 'Done'))
);
GO

-- ──────────────────────────────────────────────
-- 11. SEED DATA & INDEXES
-- ──────────────────────────────────────────────
INSERT INTO dbo.Skills (skill_name) VALUES
    (N'HTML/CSS'), (N'JavaScript'), (N'React'), (N'Vue.js'), (N'Node.js'),
    (N'Python'), (N'Flutter'), (N'React Native'), (N'Kotlin'), (N'SQL Server');

CREATE NONCLUSTERED INDEX IX_Projects_ClientID ON dbo.Projects (client_id);
CREATE NONCLUSTERED INDEX IX_WkMsg_ProjectID ON dbo.Workspace_Messages (project_id);
GO

PRINT '══════════════════════════════════════════';
PRINT '  ProjectEngine Schema REBUILT Successfully';
PRINT '  Clean slate achieved via Constraint Drop.';
PRINT '══════════════════════════════════════════';
GO
