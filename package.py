import os
import zipfile
import stat

def create_zip():
    base_dir = r"C:\Users\taner\.gemini\antigravity\scratch\cipherroom_ynh"
    zip_path = r"C:\Users\taner\.gemini\antigravity\scratch\cipherroom_ynh.zip"
    
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(base_dir):
            for file in files:
                if file == 'package.py':
                    continue
                    
                file_path = os.path.join(root, file)
                arcname = os.path.relpath(file_path, base_dir)
                # Convert backslashes to forward slashes for the zip archive
                arcname = arcname.replace('\\', '/')
                
                with open(file_path, 'rb') as f:
                    content = f.read()
                
                # Convert CRLF to LF for all text-based files
                if file_path.endswith(('.sh', '.php', '.js', '.css', '.html', '.toml', '.conf', '.md', 'LICENSE')) or 'scripts\\' in file_path or 'scripts/' in file_path:
                    content = content.replace(b'\r\n', b'\n')
                
                zinfo = zipfile.ZipInfo(arcname)
                # Make sure scripts are executable
                if arcname.startswith('scripts/'):
                    zinfo.external_attr = 0o755 << 16
                else:
                    zinfo.external_attr = 0o644 << 16
                
                zf.writestr(zinfo, content)
                print(f"Added {arcname} to zip")

if __name__ == '__main__':
    create_zip()
