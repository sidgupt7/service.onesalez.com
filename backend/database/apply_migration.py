"""Apply a simple semicolon-delimited SQL migration using DB_* environment variables."""

import json
import os
import pathlib
import sys

import pymysql


def main() -> None:
    migration_path = pathlib.Path(sys.argv[1])
    sql = migration_path.read_text(encoding="utf-8")
    statements = [statement.strip() for statement in sql.split(";") if statement.strip()]

    connection = pymysql.connect(
        host=os.environ["DB_HOST"],
        user=os.environ["DB_USER"],
        password=os.environ["DB_PASSWORD"],
        database=os.environ["DB_NAME"],
        port=int(os.environ.get("DB_PORT", "3306")),
        connect_timeout=10,
        autocommit=True,
    )

    try:
        with connection.cursor() as cursor:
            for statement in statements:
                cursor.execute(statement)

            tracked_tables = (
                "clients",
                "client_locations",
                "client_contact_roles",
                "client_contacts",
                "client_user_accounts",
                "client_contact_locations",
                "employee_roles",
                "employees",
                "employee_role_assignments",
                "employee_user_accounts",
                "service_tickets",
                "service_attempts",
                "service_status_history",
                "leads",
                "auth_refresh_tokens",
                "ticket_messages",
                "api_rate_limits",
                "teams",
                "employee_team_members",
                "permissions",
                "employee_role_permissions",
                "email_jobs",
            )
            placeholders = ", ".join(["%s"] * len(tracked_tables))
            cursor.execute(
                "SELECT table_name FROM information_schema.tables "
                "WHERE table_schema = DATABASE() "
                f"AND table_name IN ({placeholders}) "
                "ORDER BY table_name",
                tracked_tables,
            )
            tables = [row[0] for row in cursor.fetchall()]

            roles = {}
            if "client_contact_roles" in tables:
                cursor.execute(
                    "SELECT role_code FROM client_contact_roles "
                    "WHERE is_deleted = FALSE ORDER BY contact_role_id"
                )
                roles["client"] = [row[0] for row in cursor.fetchall()]
            if "employee_roles" in tables:
                cursor.execute(
                    "SELECT role_code FROM employee_roles "
                    "WHERE is_deleted = FALSE ORDER BY employee_role_id"
                )
                roles["employee"] = [row[0] for row in cursor.fetchall()]

        print(
            json.dumps(
                {
                    "statements_applied": len(statements),
                    "tables": tables,
                    "roles": roles,
                }
            )
        )
    finally:
        connection.close()


if __name__ == "__main__":
    main()
