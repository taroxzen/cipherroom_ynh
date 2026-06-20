# CipherRoom for YunoHost

[![Integration level](https://dash.yunohost.org/integration/cipherroom.svg)](https://dash.yunohost.org/appci/app/cipherroom)

*Türkçe açıklama aşağıdadır.*

CipherRoom is a secure, end-to-end encrypted (E2EE) chat application with a Material 3 dark-themed user interface.

## Features
- **Client-side Encryption:** All encryption/decryption happens directly in the browser. The server only sees ciphertexts and never learns room passwords.
- **Obfuscated Engine:** Protects client-side execution from tampering by dynamically obfuscating the JS engine on load.
- **Secure Storage:** SQLite database file is stored outside the web root (`data_dir`) for maximum security.

## Installation
To install this app on your YunoHost server, run:
```bash
sudo yunohost app install https://github.com/taroxzen/cipherroom_ynh
```

## Management
Standard YunoHost operations are fully supported:
- **Upgrade:** `sudo yunohost app upgrade cipherroom`
- **Backup:** `sudo yunohost app backup create --apps cipherroom`
- **Restore:** `sudo yunohost app backup restore <backup_name> --apps cipherroom`
- **Remove:** `sudo yunohost app remove cipherroom`

---

# YunoHost için CipherRoom

CipherRoom, Material 3 koyu temalı arayüze sahip, uçtan uca şifreli (E2EE) güvenli bir sohbet uygulamasıdır.

## Özellikler
- **İstemci Tarafında Şifreleme:** Şifreleme ve çözme işlemleri tamamen tarayıcıda yapılır. Sunucu sadece şifreli mesajları saklar, oda şifresini asla öğrenemez.
- **Gizlenmiş Kod Motoru:** Kod güvenliği için çalışan kodlar dinamik olarak karıştırılır.
- **Güvenli SQLite Depolama:** Veritabanı dosyası web dizininin dışında (`data_dir`) güvenle saklanır.

## Kurulum
Bu uygulamayı YunoHost sunucunuza kurmak için:
```bash
sudo yunohost app install https://github.com/taroxzen/cipherroom_ynh
```
