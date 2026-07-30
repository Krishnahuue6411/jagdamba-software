import os
import sys
import time
import socket
import subprocess
import tkinter as tk
from tkinter import messagebox

# Configuration
PHP_PORT = 8123
DB_PORT_PORTABLE = 3307
DB_PORT_DEFAULT = 3306
DB_NAME = "inventory_db"

if getattr(sys, 'frozen', False):
    # In PyInstaller, the executable directory is the parent of the EXE file
    BASE_DIR = os.path.dirname(sys.executable)
    # The temporary directory where PyInstaller extracts bundled files
    BUNDLE_DIR = getattr(sys, '_MEIPASS', BASE_DIR)
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))
    BUNDLE_DIR = BASE_DIR

BIN_DIR = os.path.join(BASE_DIR, "bin")

PHP_PORTABLE_EXE = os.path.join(BIN_DIR, "php", "php.exe")
MYSQLD_PORTABLE_EXE = os.path.join(BIN_DIR, "mariadb", "bin", "mysqld.exe")
MYSQL_PORTABLE_EXE = os.path.join(BIN_DIR, "mariadb", "bin", "mysql.exe")

XAMPP_PHP_EXE = "C:\\xampp\\php\\php.exe"
XAMPP_MYSQLD_EXE = "C:\\xampp\\mysql\\bin\\mysqld.exe"
XAMPP_MYSQL_EXE = "C:\\xampp\\mysql\\bin\\mysql.exe"

def is_port_in_use(port):
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        return s.connect_ex(('127.0.0.1', port)) == 0

def show_error(title, message):
    root = tk.Tk()
    root.withdraw()
    messagebox.showerror(title, message)
    root.destroy()

def show_info(title, message):
    root = tk.Tk()
    root.withdraw()
    messagebox.showinfo(title, message)
    root.destroy()

def check_runtimes():
    # 1. Check portable runtimes first
    has_portable = os.path.exists(PHP_PORTABLE_EXE) and os.path.exists(MYSQLD_PORTABLE_EXE)
    if has_portable:
        return "portable", PHP_PORTABLE_EXE, MYSQLD_PORTABLE_EXE, MYSQL_PORTABLE_EXE

    # 2. Check XAMPP runtimes as fallback
    has_xampp = os.path.exists(XAMPP_PHP_EXE) and os.path.exists(XAMPP_MYSQLD_EXE)
    if has_xampp:
        return "xampp", XAMPP_PHP_EXE, XAMPP_MYSQLD_EXE, XAMPP_MYSQL_EXE

    # 3. Not found
    return None, None, None, None

def start_db(mode, mysqld_exe):
    if mode == "portable":
        port = DB_PORT_PORTABLE
        ini_file = os.path.join(BIN_DIR, "mariadb", "my.ini")
        cmd = [mysqld_exe, f"--defaults-file={ini_file}"]
    else:
        port = DB_PORT_DEFAULT
        cmd = [mysqld_exe]

    # Check if database is already running
    if is_port_in_use(port):
        print(f"Database server is already running on port {port}.")
        return None, port

    print(f"Starting Database server ({mode} mode) on port {port}...")
    try:
        # Start DB process minimized or in background
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0 # SW_HIDE

        proc = subprocess.Popen(
            cmd,
            cwd=os.path.dirname(mysqld_exe),
            startupinfo=startupinfo,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )

        # Wait for port to open
        retries = 15
        while retries > 0:
            if is_port_in_use(port):
                print("Database server started successfully.")
                return proc, port
            time.sleep(1)
            retries -= 1

        print("[WARNING] Database port did not open in time.")
        return proc, port
    except Exception as e:
        print(f"[ERROR] Failed to start database server: {e}")
        return None, port

def setup_database_schema(mysql_exe, db_port):
    print("Verifying database schema...")
    try:
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0 # SW_HIDE

        # 1. Create database if not exists
        create_db_query = f"CREATE DATABASE IF NOT EXISTS {DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        subprocess.run(
            [mysql_exe, "-u", "root", "-P", str(db_port), "-e", create_db_query],
            startupinfo=startupinfo,
            check=True,
            capture_output=True
        )

        # 2. Check if tables exist
        check_table_query = "SHOW TABLES LIKE 'raw_material';"
        res = subprocess.run(
            [mysql_exe, "-u", "root", "-P", str(db_port), "-D", DB_NAME, "-e", check_table_query],
            startupinfo=startupinfo,
            capture_output=True,
            text=True
        )

        # 3. Import schema if tables are missing
        if "raw_material" not in res.stdout:
            schema_path = os.path.join(BUNDLE_DIR, "schema.sql")
            if not os.path.exists(schema_path):
                schema_path = os.path.join(BASE_DIR, "schema.sql")
            if os.path.exists(schema_path):
                print("Importing database schema from schema.sql...")
                with open(schema_path, "r") as schema_file:
                    subprocess.run(
                        [mysql_exe, "-u", "root", "-P", str(db_port), "-D", DB_NAME],
                        stdin=schema_file,
                        startupinfo=startupinfo,
                        check=True
                    )
                print("Database schema imported successfully.")
            else:
                print("[ERROR] schema.sql file not found. Could not initialize database tables.")
        else:
            print("Database schema is already configured.")

    except subprocess.CalledProcessError as e:
        print(f"[ERROR] Database schema configuration failed: {e.stderr}")
    except Exception as e:
        print(f"[ERROR] Database schema configuration error: {e}")

def setup_access_linking(db_port):
    print("Setting up Microsoft Access linked tables...")
    db_path = os.path.join(BASE_DIR, "sahara_electrical.accdb")

    tables = [
        "raw_material", "transport_master", "bom", "parties",
        "po_master", "po_master_copy", "inward_transaction",
        "remaining_machines", "tax_invoice", "invoice_machines",
        "workers", "machines", "daily_work", "challan_inward",
        "nfp_outward"
    ]

    vbs_tables = ", ".join([f'"{t}"' for t in tables])
    vbs_content = f'''
Dim dbPath, dbPort, tables, table, cat, tbl, fso, shell
dbPath = "{db_path.replace('\\\\', '\\\\\\\\').replace('\\', '\\\\')}"
dbPort = {db_port}
tables = Array({vbs_tables})

Set fso = CreateObject("Scripting.FileSystemObject")
If Not fso.FileExists(dbPath) Then
    Set cat = CreateObject("ADOX.Catalog")
    On Error Resume Next
    cat.Create "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=" & dbPath & ";"
    If Err.Number <> 0 Then
        Err.Clear
        cat.Create "Provider=Microsoft.ACE.OLEDB.16.0;Data Source=" & dbPath & ";"
    End If
    If Err.Number <> 0 Then
        Err.Clear
        cat.Create "Provider=Microsoft.Jet.OLEDB.4.0;Data Source=" & dbPath & ";"
    End If
    If Err.Number <> 0 Then
        WScript.Echo "Error creating Access Database: " & Err.Description
        WScript.Quit 1
    End If
    Set cat = Nothing
End If

Set cat = CreateObject("ADOX.Catalog")
On Error Resume Next
cat.ActiveConnection = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=" & dbPath & ";"
If Err.Number <> 0 Then
    Err.Clear
    cat.ActiveConnection = "Provider=Microsoft.ACE.OLEDB.16.0;Data Source=" & dbPath & ";"
End If
If Err.Number <> 0 Then
    Err.Clear
    cat.ActiveConnection = "Provider=Microsoft.Jet.OLEDB.4.0;Data Source=" & dbPath & ";"
End If
If Err.Number <> 0 Then
    WScript.Echo "Error opening Access Database: " & Err.Description
    WScript.Quit 1
End If

Dim drivers, driver, success, tblName
drivers = Array( _
    "MySQL ODBC 8.0 Unicode Driver", _
    "MySQL ODBC 8.0 ANSI Driver", _
    "MySQL ODBC 9.0 Unicode Driver", _
    "MySQL ODBC 5.3 Unicode Driver", _
    "MySQL ODBC 3.51 Driver" _
)

For Each tblName In tables
    On Error Resume Next
    cat.Tables.Delete tblName
    Err.Clear

    success = False
    For Each driver In drivers
        Set tbl = CreateObject("ADOX.Table")
        tbl.Name = tblName
        Set tbl.ParentCatalog = cat
        tbl.Properties("Jet OLEDB:Link Provider String") = "ODBC;DRIVER={" & driver & "};SERVER=127.0.0.1;PORT=" & dbPort & ";DATABASE=inventory_db;USER=root;PASSWORD=;"
        tbl.Properties("Jet OLEDB:Remote Table Name") = tblName
        cat.Tables.Append tbl

        If Err.Number = 0 Then
            success = True
            Set tbl = Nothing
            Exit For
        Else
            Err.Clear
            Set tbl = Nothing
        End If
    Next
    If Not success Then
        WScript.Echo "Failed to link table '" & tblName & "'."
        WScript.Quit 2
    End If
Next

WScript.Quit 0
'''
    import tempfile
    with tempfile.NamedTemporaryFile('w', suffix='.vbs', delete=False, encoding='utf-8') as f:
        f.write(vbs_content)
        vbs_path = f.name

    try:
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0 # SW_HIDE
        res = subprocess.run(["cscript", "/nologo", vbs_path], capture_output=True, text=True, startupinfo=startupinfo)
        if res.returncode == 0:
            print("Access database linked tables refreshed successfully.")
        elif res.returncode == 2:
            print("[WARNING] Could not link Access tables: MySQL ODBC Connector is missing on this machine.")
        else:
            print(f"[WARNING] VBScript table linking failed with code {res.returncode}: {res.stdout.strip()}")
    except Exception as e:
        print(f"[WARNING] Access table linking exception: {e}")
    finally:
        if os.path.exists(vbs_path):
            try:
                os.remove(vbs_path)
            except:
                pass

def start_php(php_exe, db_port):
    # Check if PHP port is already in use
    port = PHP_PORT
    while is_port_in_use(port):
        print(f"Port {port} is in use, trying next port...")
        port += 1

    print(f"Starting PHP built-in web server on http://127.0.0.1:{port}...")

    # Configure environment variables to pass to PHP process
    env = os.environ.copy()
    env["DB_HOST"] = f"127.0.0.1:{db_port}"
    env["DB_NAME"] = DB_NAME
    env["DB_USER"] = "root"
    env["DB_PASS"] = ""

    try:
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0 # SW_HIDE

        proc = subprocess.Popen(
            [php_exe, "-S", f"127.0.0.1:{port}", "-t", BUNDLE_DIR],
            cwd=BUNDLE_DIR,
            env=env,
            startupinfo=startupinfo,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )

        # Wait for PHP server to start
        retries = 10
        while retries > 0:
            if is_port_in_use(port):
                print("PHP web server started successfully.")
                return proc, port
            time.sleep(0.5)
            retries -= 1

        print("[ERROR] PHP port did not open.")
        return None, port
    except Exception as e:
        print(f"[ERROR] Failed to start PHP server: {e}")
        return None, port

def main():
    # 1. Check dependencies
    mode, php_exe, mysqld_exe, mysql_exe = check_runtimes()

    if not mode:
        # Runtimes not found
        msg = ("Could not find portable runtimes in 'bin/' or XAMPP on this system.\n\n"
               "Do you want to run setup_portable.py to automatically download PHP and MariaDB?\n"
               "This will configure the application to run fully standalone offline.")

        root = tk.Tk()
        root.withdraw()
        confirm = messagebox.askyesno("Runtimes Missing", msg)
        root.destroy()

        if confirm:
            setup_script = os.path.join(BASE_DIR, "setup_portable.py")
            if os.path.exists(setup_script):
                print("Starting portable runtimes setup...")
                # Run setup_portable.py in a separate command window so user can see progress
                subprocess.run(f'start cmd /k python "{setup_script}"', shell=True)
                show_info("Setup Launched", "Portable setup has been launched. Please wait for it to complete in the console window, then re-launch the application.")
                return
            else:
                show_error("Error", "Could not find setup_portable.py script in the application folder.")
                return
        else:
            return

    db_process = None
    php_process = None

    try:
        # 2. Start MySQL/MariaDB
        db_process, db_port = start_db(mode, mysqld_exe)

        # 3. Verify and import schema
        setup_database_schema(mysql_exe, db_port)

        # 3b. Setup Microsoft Access linked tables database
        setup_access_linking(db_port)

        # 4. Start PHP Built-in Server
        php_process, php_port = start_php(php_exe, db_port)

        if not php_process:
            show_error("Execution Error", "Failed to start local PHP web server.")
            return

        # 5. Launch PyWebView Browser Window
        import webview

        print("Launching desktop window...")
        webview.create_window(
            title="Sahara Electricals - Inventory & Billing System",
            url=f"http://127.0.0.1:{php_port}/index.php",
            width=1280,
            height=820,
            min_size=(950, 600),
            resizable=True
        )

        webview.start()

    except ImportError:
        # webview package is missing
        show_error("Dependency Missing", "Python library 'pywebview' is not installed. Please run this app via run_desktop.bat.")
    except Exception as e:
        show_error("Runtime Error", f"An unexpected error occurred:\n{e}")
    finally:
        # 6. Cleanup background services on exit
        print("Shutting down background services...")
        if php_process:
            print("Stopping PHP built-in web server...")
            php_process.terminate()
            php_process.wait()

        if db_process:
            print("Stopping Database server...")
            db_process.terminate()
            db_process.wait()

        print("Shutdown complete. Goodbye!")

if __name__ == "__main__":
    main()
