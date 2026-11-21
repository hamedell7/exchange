\set ON_ERROR_STOP on

-- Create service login roles only when absent.
SELECT format(
    'CREATE ROLE %I WITH LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION',
    :'identity_username', :'identity_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'identity_username')
\gexec
ALTER ROLE :"identity_username" WITH LOGIN PASSWORD :'identity_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION;

SELECT format(
    'CREATE ROLE %I WITH LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION',
    :'wallet_username', :'wallet_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'wallet_username')
\gexec
ALTER ROLE :"wallet_username" WITH LOGIN PASSWORD :'wallet_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION;

SELECT format(
    'CREATE ROLE %I WITH LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION',
    :'trading_username', :'trading_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'trading_username')
\gexec
ALTER ROLE :"trading_username" WITH LOGIN PASSWORD :'trading_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION;

-- Create databases only when absent, then enforce owner and connection ACL.
SELECT format('CREATE DATABASE %I OWNER %I', :'identity_database', :'identity_username')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'identity_database')
\gexec
ALTER DATABASE :"identity_database" OWNER TO :"identity_username";
REVOKE ALL ON DATABASE :"identity_database" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"identity_database" TO :"identity_username";

SELECT format('CREATE DATABASE %I OWNER %I', :'wallet_database', :'wallet_username')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'wallet_database')
\gexec
ALTER DATABASE :"wallet_database" OWNER TO :"wallet_username";
REVOKE ALL ON DATABASE :"wallet_database" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"wallet_database" TO :"wallet_username";

SELECT format('CREATE DATABASE %I OWNER %I', :'trading_database', :'trading_username')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'trading_database')
\gexec
ALTER DATABASE :"trading_database" OWNER TO :"trading_username";
REVOKE ALL ON DATABASE :"trading_database" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"trading_database" TO :"trading_username";
