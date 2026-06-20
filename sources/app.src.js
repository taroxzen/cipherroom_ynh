// CipherRoom - Uçtan Uca Şifreli Sohbet Engine (app.js)

// 1. Durum Yönetimi (App State)
const state = {
    nickname: '',
    roomName: '',       // Şifreyle aynı
    roomPassword: '',   // Kullanıcının manuel girdiği oda şifresi
    roomKey: null,      // Web Crypto API Key nesnesi
    roomHash: '',       // Oda şifresinin SHA-256 hash'i (sunucu araması için)
    lastMessageId: 0,   // Sunucudan çekilen son mesaj ID'si
    pollInterval: null,
    isPolling: false,
    apiUrl: 'api.php'
};

// 2. DOM Elemanları
const loginScreen = document.getElementById('login-screen');
const chatScreen = document.getElementById('chat-screen');
const loginForm = document.getElementById('login-form');
const usernameInput = document.getElementById('username-input');
const keyInput = document.getElementById('key-input');
const togglePasswordBtn = document.getElementById('toggle-password');
const joinBtn = document.getElementById('join-btn');

const activeRoomNameLabel = document.getElementById('active-room-name');
const activeRoomHashLabel = document.getElementById('active-room-hash');
const messagesContainer = document.getElementById('messages-container');
const messageForm = document.getElementById('message-form');
const messageInput = document.getElementById('message-input');
const leaveRoomBtn = document.getElementById('leave-room-btn');
const shareLinkBtn = document.getElementById('share-link-btn');

const toast = document.getElementById('toast');
const toastIcon = document.getElementById('toast-icon');
const toastText = document.getElementById('toast-text');

// 3. Kriptografi Yardımcı Fonksiyonları (Web Crypto API)

/**
 * Metnin SHA-256 Hash'ini alır (Sunucuya gerçek şifreyi sızdırmamak için)
 */
async function sha256(message) {
    const msgBuffer = new TextEncoder().encode(message);
    const hashBuffer = await window.crypto.subtle.digest('SHA-256', msgBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Şifre ve sabit bir salt kullanarak 256-bit AES-GCM şifreleme anahtarı türetir
 */
async function deriveRoomKey(password, saltString) {
    const enc = new TextEncoder();
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw",
        enc.encode(password),
        { name: "PBKDF2" },
        false,
        ["deriveKey"]
    );
    return window.crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: enc.encode(saltString),
            iterations: 100000,
            hash: "SHA-256"
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt", "decrypt"]
    );
}

/**
 * Düz metni AES-GCM ile şifreler. Base64 formatında IV ve Ciphertext döndürür.
 */
async function encryptData(plainText, key) {
    const enc = new TextEncoder();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    
    const ciphertext = await window.crypto.subtle.encrypt(
        {
            name: "AES-GCM",
            iv: iv
        },
        key,
        enc.encode(plainText)
    );
    
    const ivBase64 = btoa(String.fromCharCode(...iv));
    const ciphertextBase64 = btoa(String.fromCharCode(...new Uint8Array(ciphertext)));
    
    return {
        iv: ivBase64,
        ciphertext: ciphertextBase64
    };
}

/**
 * Base64 şifreli veriyi çözer
 */
async function decryptData(ciphertextBase64, ivBase64, key) {
    try {
        const iv = new Uint8Array(atob(ivBase64).split("").map(c => c.charCodeAt(0)));
        const ciphertext = new Uint8Array(atob(ciphertextBase64).split("").map(c => c.charCodeAt(0)));
        
        const decryptedBuffer = await window.crypto.subtle.decrypt(
            {
                name: "AES-GCM",
                iv: iv
            },
            key,
            ciphertext
        );
        
        return new TextDecoder().decode(decryptedBuffer);
    } catch (e) {
        throw new Error("Şifre çözme başarısız.");
    }
}

// 4. Arayüz ve Bildirim Fonksiyonları

function showToast(message, icon = 'info', duration = 3000) {
    toastText.textContent = message;
    toastIcon.textContent = icon;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

// Şifre görünürlüğü değiştirme
togglePasswordBtn.addEventListener('click', () => {
    const isPassword = keyInput.type === 'password';
    keyInput.type = isPassword ? 'text' : 'password';
    togglePasswordBtn.querySelector('span').textContent = isPassword ? 'visibility_off' : 'visibility';
});

// 5. URL Hash ile Davet Linki Desteği
function checkUrlHash() {
    const hash = window.location.hash;
    if (hash && hash.startsWith('#')) {
        const passwordPart = decodeURIComponent(hash.substring(1)).trim();
        if (passwordPart.length >= 4) {
            keyInput.value = passwordPart;
            // input event tetikleme
            keyInput.dispatchEvent(new Event('input'));
            usernameInput.focus();
            showToast("Davet linkinden şifreli oda bilgisi alındı!", "key");
        }
    }
}

// Sayfa yüklendiğinde URL kontrol et (Otomatik şifre üretimi kaldırıldı)
window.addEventListener('load', checkUrlHash);

// 6. Odaya Giriş İşlemi
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const nickname = usernameInput.value.trim();
    const password = keyInput.value.trim().toLowerCase(); // Şifreyi küçük harfe eşitle
    
    if (!nickname || !password) {
        showToast("Lütfen tüm alanları doldurun.", "warning");
        return;
    }
    
    joinBtn.disabled = true;
    joinBtn.querySelector('.btn-text').textContent = "Bağlanıyor...";
    
    try {
        state.nickname = nickname;
        state.roomPassword = password;
        state.roomName = password;
        
        // 1. Oda şifresinin SHA-256 Hash'ini al (Sunucu oda adı/kimliği olarak bunu görür)
        state.roomHash = await sha256(password);
        
        // 2. Kriptografik Anahtarı türet
        state.roomKey = await deriveRoomKey(password, "cipherroom-fixed-salt-2026");
        
        // Arayüz güncelle
        activeRoomNameLabel.textContent = `Oda Şifresi: ${password}`;
        activeRoomHashLabel.textContent = `Kimlik: ${state.roomHash.substring(0, 16)}...`;
        
        // Ekran geçişi
        loginScreen.classList.remove('active');
        chatScreen.classList.add('active');
        
        // Mesaj alanını temizle ve sistem mesajı ekle
        messagesContainer.innerHTML = '';
        appendSystemMessage("Şifreli odaya giriş yapıldı. Uçtan uca şifreleme devrede.");
        
        // Mesajları çekmeye başla
        state.lastMessageId = 0;
        startPolling();
        
        showToast("Odaya başarıyla girildi!", "verified_user");
    } catch (err) {
        console.error(err);
        showToast("Bağlanırken bir hata oluştu.", "error");
        joinBtn.disabled = false;
        joinBtn.querySelector('.btn-text').textContent = "Bağlan";
    }
});

// 7. Odadan Çıkış İşlemi
function leaveRoom() {
    stopPolling();
    state.nickname = '';
    state.roomName = '';
    state.roomPassword = '';
    state.roomKey = null;
    state.roomHash = '';
    state.lastMessageId = 0;
    
    joinBtn.disabled = false;
    joinBtn.querySelector('.btn-text').textContent = "Bağlan";
    window.location.hash = '';
    
    chatScreen.classList.remove('active');
    loginScreen.classList.add('active');
    showToast("Odadan çıkış yapıldı.", "logout");
}

leaveRoomBtn.addEventListener('click', leaveRoom);

// 8. Davet Linki Kopyalama
shareLinkBtn.addEventListener('click', () => {
    if (!state.roomPassword) return;
    
    const inviteUrl = `${window.location.origin}${window.location.pathname}#${encodeURIComponent(state.roomPassword)}`;
    
    navigator.clipboard.writeText(inviteUrl).then(() => {
        showToast("Güvenli oda davet linki kopyalandı! (Şifre içerir)", "content_copy");
    }).catch(err => {
        showToast("Link kopyalanamadı.", "error");
    });
});

// 9. Mesaj Gönderme İşlemi
messageForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const text = messageInput.value.trim();
    if (!text) return;
    
    messageInput.value = '';
    
    try {
        const messagePayload = JSON.stringify({
            sender: state.nickname,
            text: text
        });
        
        // Mesajı şifrele
        const encrypted = await encryptData(messagePayload, state.roomKey);
        
        // Sunucuya gönder
        const response = await fetch(state.apiUrl, {
            method: 'POST',
            headers: { ['Content-Type']: 'application/json' },
            body: JSON.stringify({
                room_hash: state.roomHash,
                iv: encrypted.iv,
                ciphertext: encrypted.ciphertext
            })
        });
        
        if (!response.ok) {
            throw new Error("Mesaj sunucuya gönderilemedi.");
        }
        
        fetchMessages();
    } catch (err) {
        console.error(err);
        showToast("Mesaj gönderilemedi.", "error");
    }
});

// 10. Mesaj Çekme ve Polling
async function fetchMessages() {
    if (!state.roomHash || !state.roomKey) return;
    
    try {
        const response = await fetch(`${state.apiUrl}?room=${state.roomHash}&since=${state.lastMessageId}`);
        if (!response.ok) {
            throw new Error("Mesajlar çekilemedi.");
        }
        
        const data = await response.json();
        const messages = data.messages || [];
        
        if (messages.length > 0) {
            let processedAny = false;
            
            for (const msg of messages) {
                if (msg.id > state.lastMessageId) {
                    state.lastMessageId = msg.id;
                }
                
                try {
                    const decryptedJson = await decryptData(msg.ciphertext, msg.iv, state.roomKey);
                    const payload = JSON.parse(decryptedJson);
                    
                    appendMessage(payload.sender, payload.text, msg.created_at);
                    processedAny = true;
                } catch (decryptionError) {
                    appendMessageError(msg.created_at);
                    processedAny = true;
                }
            }
            
            if (processedAny) {
                scrollToBottom();
            }
        }
    } catch (err) {
        console.error("Poller hatası:", err);
    }
}

function startPolling() {
    if (state.isPolling) return;
    state.isPolling = true;
    
    fetchMessages();
    state.pollInterval = setInterval(fetchMessages, 2000);
}

function stopPolling() {
    if (state.pollInterval) {
        clearInterval(state.pollInterval);
        state.pollInterval = null;
    }
    state.isPolling = false;
}

// 11. DOM Arayüz Ekleme Yardımcıları

function appendSystemMessage(text) {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'system-message';
    msgDiv.innerHTML = `
        <span class="material-symbols-outlined message-lock-icon">lock</span>
        <p>${text}</p>
    `;
    messagesContainer.appendChild(msgDiv);
    scrollToBottom();
}

function appendMessage(sender, text, timestamp) {
    const isMe = sender === state.nickname;
    
    const wrapper = document.createElement('div');
    wrapper.className = `message-wrapper ${isMe ? 'sent' : 'received'}`;
    
    const meta = document.createElement('div');
    meta.className = 'message-meta';
    meta.textContent = `${sender} • ${formatTime(timestamp)}`;
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.textContent = text;
    
    wrapper.appendChild(meta);
    wrapper.appendChild(bubble);
    messagesContainer.appendChild(wrapper);
}

function appendMessageError(timestamp) {
    const wrapper = document.createElement('div');
    wrapper.className = 'message-wrapper received';
    
    const meta = document.createElement('div');
    meta.className = 'message-meta';
    meta.textContent = `Bilinmeyen Gönderici • ${formatTime(timestamp)}`;
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble error-bubble';
    bubble.textContent = "[Şifreli Mesaj: Anahtarınız bu mesajı çözemedi.]";
    
    wrapper.appendChild(meta);
    wrapper.appendChild(bubble);
    messagesContainer.appendChild(wrapper);
}

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function formatTime(unixTimestamp) {
    const date = new Date(unixTimestamp * 1000);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
}
