#------------------------------------------------------------------------------
# An existing PostgreSQL server
#
# A server that runs PostgreSQL already (or --db-host, for one on another
# server) keeps it: the installer asks how to reach it, and checks that it
# can hold Chatvia before anything changes. It never installs PostgreSQL or
# changes its settings. It creates Chatvia's databases and extensions there
# when the database user may; what the user may not do is printed as SQL for
# a superuser to run, and the install stops until that is done.
#------------------------------------------------------------------------------

# DATABASE=existing for --db-host, a PostgreSQL installed on this server, or
# one that listens on its port (in a container, say).
native_detect_database() {
    DATABASE=local
    if [ -n "${CHATVIA_DB_HOST:-}" ] || [ -n "$(ls -A /etc/postgresql 2>/dev/null)" ]; then
        DATABASE=existing
    elif command -v ss >/dev/null 2>&1 && [ -n "$(ss -Hltn '( sport = :5432 )' 2>/dev/null)" ]; then
        DATABASE=existing
    fi
}

# The port of this server's PostgreSQL: its first running cluster's.
local_pg_port() {
    local port=''
    if command -v pg_lsclusters >/dev/null 2>&1; then
        port=$(pg_lsclusters -h 2>/dev/null | awk '$4 == "online" { print $3; exit }') || port=''
    fi
    printf '%s' "${port:-5432}"
}

valid_db_host() {
    if [[ $1 =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || [[ $1 =~ ^[0-9A-Fa-f:]*:[0-9A-Fa-f:]*$ ]]; then
        return 0
    fi
    INVALID='Type a host name or an IP address, like 127.0.0.1 or db.example.com.'
    return 1
}

valid_db_port() {
    if [[ $1 =~ ^[0-9]{1,5}$ ]] && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]; then return 0; fi
    INVALID='Use a number from 1 to 65535.'
    return 1
}

# At most 49 characters, so the name with _analytics still fits in 63.
valid_db_name() {
    if [[ $1 =~ ^[a-z_][a-z0-9_]{0,48}$ ]]; then return 0; fi
    INVALID='Use lowercase letters, digits and _ (at most 49), starting with a letter or _.'
    return 1
}

valid_db_user() {
    if [[ $1 =~ ^[A-Za-z0-9_][A-Za-z0-9_.@-]{0,62}$ ]]; then return 0; fi
    INVALID='Use letters, digits, _, ., @ and - only.'
    return 1
}

# .env holds the password in single quotes, which cannot hold one itself.
valid_db_password() {
    if [[ $1 == *"'"* ]] || [[ $1 == *$'\n'* ]]; then
        INVALID="The password has a ' in it, which .env cannot hold. Give the user another password."
        return 1
    fi
    return 0
}

# The analytics, Pulse and Spectra databases are named after the app's, so
# they do not meet another app's databases on a shared server.
native_name_databases() {
    DB_ANALYTICS="${DB_NAME}_analytics"
    DB_PULSE="${DB_NAME}_pulse"
    DB_SPECTRA="${DB_NAME}_spectra"
}

# db_psql DATABASE ARGS...: psql on the existing server, as the database
# user. The password goes through the environment, never the command line.
db_psql() {
    local database=$1
    shift
    PGPASSWORD=$DB_PASS PGCONNECT_TIMEOUT=10 PGAPPNAME=chatvia-installer \
        psql -X -q -v ON_ERROR_STOP=1 -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$database" "$@"
}

# db_dump DATABASE: a pg_dump of it, in PostgreSQL's custom format, on stdout.
db_dump() {
    PGPASSWORD=$DB_PASS PGCONNECT_TIMEOUT=10 \
        pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -Fc "$1"
}

# The password saved in the shared .env, without its quotes.
native_db_password() {
    local value
    value=$(env_value DB_PASSWORD)
    value=${value#\'}
    printf '%s' "${value%\'}"
}

native_install_pg_client() {
    apt_get update
    apt_get install postgresql-client
}

native_database_settings() {
    DB_HOST=${CHATVIA_DB_HOST:-127.0.0.1}
    DB_PORT=${CHATVIA_DB_PORT:-}
    if [ -z "$DB_PORT" ]; then
        DB_PORT=5432
        if [ -z "${CHATVIA_DB_HOST:-}" ]; then DB_PORT=$(local_pg_port); fi
    fi
    DB_NAME=${CHATVIA_DB_DATABASE:-chatvia}
    DB_USER=${CHATVIA_DB_USERNAME:-chatvia}
    DB_PASS=${CHATVIA_DB_PASSWORD:-}

    if [ "$INTERACTIVE" = 0 ]; then
        valid_db_host "$DB_HOST" || die "--db-host: $INVALID"
        valid_db_port "$DB_PORT" || die "--db-port: $INVALID"
        valid_db_name "$DB_NAME" || die "--db-name: $INVALID"
        valid_db_user "$DB_USER" || die "--db-user: $INVALID"
        [ -n "$DB_PASS" ] || die "Set the password of the database user $DB_USER: CHATVIA_DB_PASSWORD."
        valid_db_password "$DB_PASS" || die "$INVALID"
        native_name_databases
        native_check_database || die "Could not connect to PostgreSQL at $DB_HOST:$DB_PORT as $DB_USER." "$DB_ERROR"
        return
    fi

    section 'Your PostgreSQL server'
    if [ -n "${CHATVIA_DB_HOST:-}" ]; then
        info "ChatVia.ai uses the PostgreSQL server at $(bold "$DB_HOST"). It installs no PostgreSQL of its own."
    else
        info 'PostgreSQL runs on this server already, so ChatVia.ai uses it. It installs'
        info 'no PostgreSQL of its own and leaves its settings as they are.'
    fi
    info "It needs PostgreSQL $NATIVE_MIN_PG or newer with pgvector, pg_textsearch and pg_partman."
    while true; do
        ask DB_HOST 'PostgreSQL host' --default "$DB_HOST" \
            --hint '127.0.0.1 for the PostgreSQL on this server.' --check valid_db_host
        ask DB_PORT 'PostgreSQL port' --default "$DB_PORT" --check valid_db_port
        ask DB_NAME 'Database name' --default "$DB_NAME" --check valid_db_name \
            --hint 'ChatVia.ai also uses three more next to it: NAME_analytics, NAME_pulse and NAME_spectra.'
        ask DB_USER 'Database user' --default "$DB_USER" --check valid_db_user \
            --hint 'A user that exists already. The databases are created for it when it may create databases.'
        while true; do
            ask_secret DB_PASS "Password of $DB_USER" once
            valid_db_password "$DB_PASS" && break
            warn "$INVALID"
        done
        native_name_databases
        native_check_database && return
        warn "Could not connect to PostgreSQL at $DB_HOST:$DB_PORT as $DB_USER."
        printf '%s\n' "$DB_ERROR" | quote
        choose 'What do you want to do?' \
            'Type the details again' \
            'Stop for now  — nothing was changed'
        if [ "$CHOICE" != 0 ]; then
            outro 'Nothing was changed.'
            exit 0
        fi
    done
}

# Connects to the existing server and checks, without changing anything,
# that it can hold Chatvia: its version, the extensions on it, pg_textsearch
# loaded when it starts, and that the user may create what is missing. A
# connection that fails returns 1, with psql's message in DB_ERROR. Anything
# else that is wrong stops the install, with what to do about it.
native_check_database() {
    local facts version_num version super createdb connect_to='' database
    local preload='' available='' trusted='' listed='' problems=() sql_dbs=() sql=() packages=() created=()
    DB_ERROR=''

    if ! command -v psql >/dev/null 2>&1; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "psql is not installed, so the database is not checked. $(dim '(dry run)')"
            return 0
        fi
        run_step 'Installing the PostgreSQL client (psql) to check the database' native_install_pg_client ||
            die 'The PostgreSQL client could not be installed.' "See why: $LOG"
    fi

    # The app's database, or, while it does not exist, a database that is
    # always there, to read the rest.
    connect_to=$(native_db_reachable 2>&1) || {
        DB_ERROR=$connect_to
        return 1
    }

    facts=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT current_setting('server_version_num'), current_setting('server_version'), rolsuper, rolcreatedb
         FROM pg_roles WHERE rolname = current_user") || {
        DB_ERROR='Could not read the server version and the rights of the user.'
        return 1
    }
    IFS='|' read -r version_num version super createdb <<<"$facts"
    version=${version%% *}
    DB_MAJOR=$((version_num / 10000))
    if [ "$DB_MAJOR" -lt "$NATIVE_MIN_PG" ]; then
        die "The PostgreSQL server at $DB_HOST:$DB_PORT is version $version. ChatVia.ai needs PostgreSQL $NATIVE_MIN_PG or newer." \
            'Upgrade it, or use another PostgreSQL server. Nothing was changed.'
    fi
    ok "PostgreSQL $version at $DB_HOST:$DB_PORT $(dim "(user $DB_USER)")"

    # Which extensions the server has, and which of them a database owner
    # may create without being a superuser (trusted).
    available=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT e.name, v.trusted FROM pg_available_extensions e
         JOIN pg_available_extension_versions v ON v.name = e.name AND v.version = e.default_version") || available=''
    local extension
    for extension in $APP_EXTENSIONS pg_partman; do
        if ! grep -q "^$extension|" <<<"$available"; then
            case $extension in
                vector) packages+=("pgvector  $(dim "(Ubuntu: postgresql-$DB_MAJOR-pgvector)")") ;;
                pg_partman) packages+=("pg_partman  $(dim "(Ubuntu: postgresql-$DB_MAJOR-partman)")") ;;
                pg_textsearch) packages+=("pg_textsearch  $(dim "($PG_TEXTSEARCH_RELEASES)")") ;;
                *) packages+=("$extension  $(dim '(comes with PostgreSQL itself)')") ;;
            esac
        elif grep -q "^$extension|t$" <<<"$available"; then
            trusted="$trusted $extension"
        fi
    done
    if [ "${#packages[@]}" -gt 0 ]; then
        problems+=('These extensions are not installed on the PostgreSQL server:')
        for extension in "${packages[@]}"; do problems+=("  - $extension"); done
    fi

    # Only a superuser (or pg_read_all_settings) may read this. When it
    # cannot be read, creating pg_textsearch tells.
    preload=$(db_psql "$connect_to" -Atc "SELECT current_setting('shared_preload_libraries')" 2>/dev/null) || preload='?'
    if [ "$preload" != '?' ] && ! tr ',' '\n' <<<"$preload" | tr -d ' "' | grep -qx pg_textsearch; then
        problems+=('pg_textsearch is not loaded when PostgreSQL starts. Add it to shared_preload_libraries'
            "in postgresql.conf (now: '${preload}'), then restart PostgreSQL.")
    fi

    # The databases: there already, or created for the user when it may.
    listed=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT datname, has_database_privilege(datname, 'CONNECT'), has_database_privilege(datname, 'CREATE')
         FROM pg_database WHERE datname IN ('$DB_NAME', '$DB_ANALYTICS', '$DB_PULSE', '$DB_SPECTRA')") || listed=''
    local row can_connect can_create details schema_ok installed tables chatvia_tables needed absent=() ext create_it
    for database in "$DB_NAME" "$DB_ANALYTICS" "$DB_PULSE" "$DB_SPECTRA"; do
        case $database in
            "$DB_NAME") needed=$APP_EXTENSIONS ;;
            "$DB_ANALYTICS") needed=pg_partman ;;
            *) needed='' ;;
        esac
        row=$(grep "^$database|" <<<"$listed") || row=''
        installed='' create_it=0
        if [ -z "$row" ]; then
            if [ "$super" = t ] || [ "$createdb" = t ]; then
                create_it=1
                can_create=t
            else
                sql_dbs+=("CREATE DATABASE $database OWNER \"$DB_USER\";")
                can_create=f
            fi
        else
            IFS='|' read -r _ can_connect can_create <<<"$row"
            details=''
            if [ "$can_connect" = t ]; then
                details=$(db_psql "$database" -At -F '|' -c \
                    "SELECT has_schema_privilege('public', 'CREATE'),
                            (SELECT coalesce(string_agg(extname, ' '), '') FROM pg_extension),
                            (SELECT count(*) FROM pg_tables WHERE schemaname = 'public'),
                            to_regclass('public.agents') IS NOT NULL" 2>/dev/null) || details=''
            fi
            IFS='|' read -r schema_ok installed tables chatvia_tables <<<"$details"
            if [ "$can_connect" != t ] || [ "$can_create" != t ] || [ "$schema_ok" != t ]; then
                sql+=("ALTER DATABASE $database OWNER TO \"$DB_USER\";")
                can_create=f
            fi
            if [ "$database" = "$DB_NAME" ] && [ "${tables:-0}" -gt 0 ] && [ "$chatvia_tables" != t ]; then
                problems+=("The database $database holds the tables of another app. Use an empty database, or another name.")
            fi
        fi

        # The extensions it still needs (of those on the server), and who
        # may create them.
        absent=()
        for ext in $needed; do
            [[ " $installed " == *" $ext "* ]] && continue
            grep -q "^$ext|" <<<"$available" || continue
            if [ "$super" = t ] || { [ "$can_create" = t ] && [[ " $trusted " == *" $ext "* ]]; }; then continue; fi
            absent+=("$ext")
        done
        if [ "${#absent[@]}" -gt 0 ]; then
            # The superuser can only connect to it once it is there.
            if [ "$create_it" = 1 ]; then
                create_it=0
                sql_dbs+=("CREATE DATABASE $database OWNER \"$DB_USER\";")
            fi
            sql+=("\\connect $database")
            for ext in "${absent[@]}"; do
                if [ "$ext" = pg_partman ]; then
                    sql+=("CREATE SCHEMA IF NOT EXISTS partman AUTHORIZATION \"$DB_USER\";"
                        'CREATE EXTENSION IF NOT EXISTS pg_partman SCHEMA partman;'
                        "GRANT ALL ON ALL TABLES IN SCHEMA partman TO \"$DB_USER\";"
                        "GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA partman TO \"$DB_USER\";"
                        "GRANT EXECUTE ON ALL PROCEDURES IN SCHEMA partman TO \"$DB_USER\";")
                else
                    sql+=("CREATE EXTENSION IF NOT EXISTS $ext;")
                fi
            done
        fi
        if [ "$create_it" = 1 ]; then created+=("$database"); fi
    done

    # Backups (and the backup before each upgrade) need a pg_dump of the
    # server's version or newer: from Ubuntu, or from the PostgreSQL archive,
    # which the install adds when Ubuntu lacks it.
    if command -v apt-cache >/dev/null 2>&1 && [ ! -x "/usr/lib/postgresql/$DB_MAJOR/bin/pg_dump" ] &&
        ! apt_has_candidate "postgresql-client-$DB_MAJOR" && ! pgdg_carries_this_release; then
        problems+=("There is no package postgresql-client-$DB_MAJOR for backups of PostgreSQL $DB_MAJOR. Add the"
            'PostgreSQL apt repository (https://wiki.postgresql.org/wiki/Apt), then run the installer again.')
    fi

    sql=(${sql_dbs[@]+"${sql_dbs[@]}"} ${sql[@]+"${sql[@]}"})
    if [ "${#problems[@]}" -eq 0 ] && [ "${#sql[@]}" -eq 0 ]; then
        ok 'pgvector, pg_textsearch and pg_partman are on the server'
        if [ "${#created[@]}" -gt 0 ]; then
            info "$(dim "The install creates the databases ${created[*]}")"
        fi
        return 0
    fi

    # On stderr, like the failure itself, so the two never come apart.
    {
        fail 'This PostgreSQL server cannot hold ChatVia.ai yet.'
        if [ "${#problems[@]}" -gt 0 ]; then
            local line
            for line in "${problems[@]}"; do info "$line"; done
        fi
        if [ "${#sql[@]}" -gt 0 ]; then
            block text
            info "$DB_USER may not do this itself. A superuser runs it in psql:"
            if [ "$preload" = '?' ] && [[ ${sql[*]} == *pg_textsearch* ]]; then
                info "$(dim 'pg_textsearch must be in shared_preload_libraries first, or creating it fails.')"
            fi
            printf '%s\n' "${sql[@]}" | quote
        fi
    } >&2
    die 'Nothing was changed.' 'Fix these, then run the installer again.'
}

# The first database the user can connect to, on stdout: the app's, or,
# while that does not exist yet, one that is always there. Otherwise psql's
# message, and 1.
native_db_reachable() {
    local database output
    for database in "$DB_NAME" postgres template1; do
        if output=$(db_psql "$database" -Atc 'SELECT 1' 2>&1); then
            printf '%s' "$database"
            return 0
        fi
        [[ $output == *"database \"$database\" does not exist"* ]] || break
    done
    printf '%s\n' "$output"
    return 1
}

# Creates what the checks found missing: the databases (when the user may
# create databases) and the extensions (when it may create them). Safe to
# repeat, like every step.
native_prepare_database() {
    local database ext via
    via=$(native_db_reachable) || {
        echo "$via" >&2
        return 1
    }
    for database in "$DB_NAME" "$DB_ANALYTICS" "$DB_PULSE" "$DB_SPECTRA"; do
        if [ -z "$(db_psql "$via" -Atc "SELECT 1 FROM pg_database WHERE datname = '$database'")" ]; then
            db_psql "$via" -c "CREATE DATABASE $database"
            echo "Created the database $database."
        fi
    done
    for ext in $APP_EXTENSIONS; do
        db_psql "$DB_NAME" -c "CREATE EXTENSION IF NOT EXISTS $ext"
    done
    db_psql "$DB_ANALYTICS" -c 'CREATE SCHEMA IF NOT EXISTS partman' \
        -c 'CREATE EXTENSION IF NOT EXISTS pg_partman SCHEMA partman'
    db_psql "$DB_NAME" -Atc "SELECT extname || ' ' || extversion FROM pg_extension ORDER BY 1"
}

pgdg_carries_this_release() {
    local release
    # shellcheck source=/dev/null
    release=$(. /etc/os-release 2>/dev/null && printf '%s' "${VERSION_CODENAME:-}")
    [ -n "$release" ] && curl -fsI --max-time 10 "$PGDG_URL/dists/$release-pgdg/Release" >/dev/null 2>&1
}
