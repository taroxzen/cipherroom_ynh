CipherRoom is a lightweight, secure, and end-to-end encrypted (E2EE) chat application.

### Features
- **End-to-End Encryption (E2EE):** All messages are encrypted directly in the browser using the Web Crypto API (AES-GCM 256-bit with PBKDF2 key derivation) before being transmitted. The server never learns the room password or reads the messages.
- **Obfuscated Engine:** The frontend application logic is dynamically obfuscated on every page load to prevent tampering and reverse-engineering of the security flow.
- **Secure Storage:** The message database (SQLite) is stored outside the web root (`data_dir` resource), making it inaccessible to the public web server.
- **Material 3 UI:** Modern dark-theme chat interface built using responsive Material Design 3 guidelines.
