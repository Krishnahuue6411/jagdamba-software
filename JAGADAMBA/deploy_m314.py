import os
import shutil
import sys

def main():
    # Source is the current directory
    src = os.path.dirname(os.path.abspath(__file__))
    # Destination directory name
    dst = os.path.join(os.path.dirname(src), "sahara electrical - M314")

    print(f"Deploying cloned software instance to: {dst}...")

    # Ignore system dotfiles, git history, database storage folders, and uploaded custom signatures during copy
    def ignore_patterns(path, names):
        ignored = []
        for name in names:
            if name in ['.git', '.agents', '.gemini', 'uploads', 'signature.png', 'node_modules', 'copy_qr.php']:
                ignored.append(name)
        return ignored

    if os.path.exists(dst):
        print("M314 destination folder already exists. Re-deploying fresh clone...")
        try:
            shutil.rmtree(dst)
        except Exception as e:
            print(f"Error cleaning existing destination: {e}")
            sys.exit(1)

    try:
        shutil.copytree(src, dst, ignore=ignore_patterns)
        print("Workspace copied successfully.")
    except Exception as e:
        print(f"Failed to copy workspace directory: {e}")
        sys.exit(1)

    # 1. Customize launcher.py in destination folder
    launcher_path = os.path.join(dst, "launcher.py")
    if os.path.exists(launcher_path):
        print("Configuring launcher.py for M314 environment...")
        with open(launcher_path, 'r', encoding='utf-8') as f:
            content = f.read()

        # Isolate database and PHP web ports to prevent conflict
        content = content.replace('DB_NAME = "inventory_db"', 'DB_NAME = "inventory_db_m314"')
        content = content.replace('PHP_PORT = 8123', 'PHP_PORT = 8124')
        content = content.replace('Sahara Electricals - Inventory & Billing System', 'Sahara Electricals - Inventory & Billing System (M314)')

        with open(launcher_path, 'w', encoding='utf-8') as f:
            f.write(content)

    # 2. Customize default UI selectors inside PHP files for M314
    for fname in ["inward.php", "tax_invoice.php"]:
        fpath = os.path.join(dst, fname)
        if os.path.exists(fpath):
            print(f"Configuring default part selections in {fname} to M314...")
            with open(fpath, 'r', encoding='utf-8') as f:
                code = f.read()

            # Replace default selection from M311 to M314
            code = code.replace("let currentPartNo = 'M311';", "let currentPartNo = 'M314';")
            code = code.replace("defaultPart = 'M311'", "defaultPart = 'M314'")
            code = code.replace("'M311' === 'M311'", "'M314' === 'M311'")

            with open(fpath, 'w', encoding='utf-8') as f:
                f.write(code)

    # 3. Configure db_config.php for M314_ONLY environment
    db_config_path = os.path.join(dst, "db_config.php")
    if os.path.exists(db_config_path):
        print("Configuring db_config.php to enable M314_ONLY mode...")
        with open(db_config_path, 'r', encoding='utf-8') as f:
            code = f.read()

        code = code.replace("define('M314_ONLY', false);", "define('M314_ONLY', true);")

        with open(db_config_path, 'w', encoding='utf-8') as f:
            f.write(code)

    print("\n==============================================================")
    print("SUCCESS: M314 Instance Deployed Successfully!")
    print("==============================================================")
    print(f"Directory: {dst}")
    print("Port: 8124")
    print("Database: inventory_db_m314")
    print("To launch: Open the cloned folder and double-click 'run_desktop.bat'")
    print("==============================================================\n")

if __name__ == "__main__":
    main()
