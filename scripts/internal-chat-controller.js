/**
 * InternalChatController
 * Elite Standard for Staff-Admin communications
 */
class ChatController {
    constructor(userId, userRole, threadId = null) {
        this.userId = userId;
        this.userRole = userRole;
        this.currentThreadId = threadId;
        this.pollingInterval = null;

        // DOM
        this.chatMessages = document.getElementById('chatMessages');
        this.inputArea = document.getElementById('messageInput');
        this.attachBtn = document.getElementById('attachBtn');
        this.fileInput = document.getElementById('fileInput');

        this.init();
    }

    init() {
        if (this.currentThreadId) {
            this.fetchMessages();
            this.startPolling();
        }

        // Enter to Send
        if (this.inputArea) {
            this.inputArea.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        // Attachments
        if (this.attachBtn) this.attachBtn.onclick = () => this.fileInput.click();
        if (this.fileInput) {
            this.fileInput.onchange = () => {
                if (this.fileInput.files.length > 0) this.sendMessage();
            };
        }

        // Global Lightbox
        this.initLightbox();
    }

    startPolling() {
        this.pollingInterval = setInterval(() => this.fetchMessages(), 3000);
    }

    async fetchMessages() {
        if (!this.currentThreadId) return;
        try {
            const res = await fetch(`${BASE_URL}handlers/get_internal_messages.php?thread_id=${this.currentThreadId}`);
            const data = await res.json();
            if (data.success) {
                this.renderMessages(data.messages);
            }
        } catch (e) {}
    }

    renderMessages(messages) {
        if (!this.chatMessages) return;
        messages.forEach(msg => {
            if (!document.getElementById(`msg-${msg.id}`)) {
                const isMe = msg.type === 'sent';
                let content = `<div class="message-text">${this.escapeHtml(msg.message)}</div>`;

                if (msg.message_type === 'image' && msg.attachment_path) {
                    content = `<div class="message-text"><img src="${msg.attachment_path}" class="chat-img-zoomable" alt="Attachment"></div>`;
                } else if (msg.message_type === 'file' && msg.attachment_path) {
                    content = `<div class="message-text clinical-file-msg"><a href="${msg.attachment_path}" target="_blank"><i class="fas fa-file-pdf"></i> ${msg.file_name}</a></div>`;
                }

                const html = `
                    <div class="message-wrapper ${isMe ? 'sent' : 'received'}" id="msg-${msg.id}">
                        <div class="message-bubble">
                            <div style="font-size:0.7rem; color:var(--primary-green); margin-bottom:2px; font-weight:600;">${msg.sender_name}</div>
                            ${content}
                            <div class="msg-time">${msg.pretty_time}</div>
                        </div>
                    </div>
                `;
                this.chatMessages.insertAdjacentHTML('beforeend', html);
                this.scrollToBottom();
            }
        });
    }

    async sendMessage() {
        const text = this.inputArea.value.trim();
        const file = this.fileInput.files[0];
        if (!text && !file) return;

        const formData = new FormData();
        formData.append('thread_id', this.currentThreadId);
        formData.append('message', text);
        if (file) formData.append('attachment', file);

        this.inputArea.value = '';
        this.fileInput.value = '';

        try {
            const res = await fetch(`${BASE_URL}handlers/send_internal_message.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                this.renderMessages([data.message]);
            }
        } catch (e) { alert("Communication error."); }
    }

    scrollToBottom() {
        if (this.chatMessages) this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML.replace(/\n/g, '<br>');
    }

    initLightbox() {
        // Create lightbox if doesn't exist
        if (!document.getElementById('lightboxOverlay')) {
            const lb = document.createElement('div');
            lb.id = 'lightboxOverlay';
            lb.className = 'lightbox-overlay';
            lb.innerHTML = '<img class="lightbox-content" src="">';
            document.body.appendChild(lb);
            lb.onclick = () => lb.style.display = 'none';
        }

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('chat-img-zoomable')) {
                const lb = document.getElementById('lightboxOverlay');
                const lbImg = lb.querySelector('img');
                lbImg.src = e.target.src;
                lb.style.display = 'flex';
            }
        });
    }
}
