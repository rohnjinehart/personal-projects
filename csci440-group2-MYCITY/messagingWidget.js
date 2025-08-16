// messagingWidget.js
class MessagingWidget {
    constructor() {
        this.widgetOpen = false;
        this.currentConversation = null;
        this.unreadCount = 0;
        this.currentUserId = document.body.getAttribute('data-user-id') || 0;
        this.initialize();
    }
    
    initialize() {
        // Load initial conversations
        this.loadConversations();
        
        // Poll for new messages every 10 seconds
        setInterval(() => this.checkNewMessages(), 10000);
    }
    
    showFullWidget() {
        // Create widget HTML only when needed
        if (!document.querySelector('.messaging-widget')) {
            const widgetHtml = `
                <div class="messaging-widget">
                    <div class="messaging-container">
                        <div class="messaging-header">
                            <h5>Messages</h5>
                            <button class="btn-close" onclick="window.messagingWidget.toggleWidget()">&times;</button>
                        </div>
                        <div class="conversation-list"></div>
                        <div class="message-view" style="display: none;">
                            <div class="message-header">
                                <button class="btn btn-sm btn-back" onclick="window.messagingWidget.showConversationList()">
                                    <i class="bi bi-arrow-left"></i>
                                </button>
                                <h5 class="conversation-title"></h5>
                            </div>
                            <div class="messages-container"></div>
                            <div class="message-input">
                                <textarea class="form-control" placeholder="Type your message..."></textarea>
                                <button class="btn btn-primary btn-send">Send</button>
                            </div>
                        </div>
                        <div class="new-conversation" style="display: none;">
                            <div class="new-conversation-header">
                                <button class="btn btn-sm btn-back" onclick="window.messagingWidget.showConversationList()">
                                    <i class="bi bi-arrow-left"></i>
                                </button>
                                <h5>New Message</h5>
                            </div>
                            <div class="user-search-container">
                                <input type="text" class="form-control user-search-input" placeholder="Search users...">
                            </div>
                            <div class="user-list"></div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', widgetHtml);
            
            // Attach event listeners
            document.querySelector('.btn-send')?.addEventListener('click', () => this.sendMessage());
            document.querySelector('.message-input textarea')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            document.querySelector('.user-search-input')?.addEventListener('input', (e) => {
                this.filterUserList(e.target.value);
            });
        }
        
        this.widgetOpen = true;
        document.querySelector('.messaging-widget').classList.add('show');
        this.loadConversations();
    }
    
    toggleWidget() {
        if (!document.querySelector('.messaging-widget')) {
            this.showFullWidget();
            return;
        }
        
        this.widgetOpen = !this.widgetOpen;
        const widget = document.querySelector('.messaging-widget');
        widget.classList.toggle('show', this.widgetOpen);
        
        if (this.widgetOpen) {
            this.loadConversations();
        }
    }
    
    async loadConversations() {
        try {
            const response = await this.fetchData('get_conversations');
            this.renderConversationList(response.conversations);
            this.updateUnreadCount(response.conversations);
        } catch (error) {
            console.error('Error loading conversations:', error);
        }
    }
    
    renderConversationList(conversations) {
        const container = document.querySelector('.conversation-list');
        if (!container) return;
        
        container.innerHTML = '';
        
        // Always show the new conversation button at the top
        const newConvBtn = document.createElement('div');
        newConvBtn.className = 'new-conversation-btn';
        newConvBtn.innerHTML = '<button class="btn btn-primary"><i class="bi bi-plus"></i> New Message</button>';
        newConvBtn.addEventListener('click', () => this.showNewConversation());
        container.appendChild(newConvBtn);
        
        if (conversations.length === 0) {
            const noConvs = document.createElement('div');
            noConvs.className = 'no-conversations';
            noConvs.textContent = 'No conversations yet';
            container.appendChild(noConvs);
            return;
        }
        
        conversations.forEach(conv => {
            const convEl = document.createElement('div');
            convEl.className = 'conversation-item';
            convEl.innerHTML = `
                <div class="conversation-info" onclick="window.messagingWidget.openConversation(${conv.id}, ${conv.other_user_id}, '${this.escapeHtml(conv.other_user_name)}')">
                    <div class="conversation-user">${this.escapeHtml(conv.other_user_name)}</div>
                    <div class="conversation-preview">${this.escapeHtml(conv.last_message || 'No messages yet')}</div>
                </div>
                <div class="conversation-time">${this.formatTime(conv.updated_at)}</div>
            `;
            container.appendChild(convEl);
        });
        
        // Show conversation list and hide other views
        document.querySelector('.conversation-list').style.display = 'block';
        document.querySelector('.message-view').style.display = 'none';
        document.querySelector('.new-conversation').style.display = 'none';
    }
    
    async openConversation(conversationId, userId, userName) {
        this.currentConversation = { id: conversationId, userId, userName };
        
        try {
            const response = await this.fetchData('get_messages', { conversation_id: conversationId });
            this.renderMessages(response.messages);
            
            // Update UI to show message view
            document.querySelector('.conversation-list').style.display = 'none';
            document.querySelector('.new-conversation').style.display = 'none';
            document.querySelector('.message-view').style.display = 'block';
            document.querySelector('.conversation-title').textContent = userName;
            
            // Scroll to bottom of messages
            this.scrollMessagesToBottom();
        } catch (error) {
            console.error('Error opening conversation:', error);
        }
    }
    
    renderMessages(messages) {
        const container = document.querySelector('.messages-container');
        if (!container) return;
        
        const wasAtBottom = this.isScrolledToBottom(container);
        
        container.innerHTML = '';
        
        messages.forEach(msg => {
            const msgEl = document.createElement('div');
            msgEl.className = `message ${msg.sender_id == this.currentUserId ? 'sent' : 'received'}`;
            msgEl.innerHTML = `
                <div class="message-content">${this.escapeHtml(msg.content)}</div>
                <div class="message-meta">
                    <span class="message-time">${this.formatTime(msg.created_at)}</span>
                    ${msg.sender_id == this.currentUserId ? '' : `<span class="message-sender">${this.escapeHtml(msg.sender_name)}</span>`}
                </div>
            `;
            container.appendChild(msgEl);
        });
        
        if (wasAtBottom) {
            this.scrollMessagesToBottom();
        }
    }
    
    isScrolledToBottom(element) {
        if (!element) return false;
        return element.scrollHeight - element.clientHeight <= element.scrollTop + 1;
    }
    
    scrollMessagesToBottom() {
        const container = document.querySelector('.messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
    
    async sendMessage() {
        const input = document.querySelector('.message-input textarea');
        if (!input || !this.currentConversation) return;
        
        const content = input.value.trim();
        if (!content) return;
        
        try {
            const response = await this.fetchData('send_message', {
                conversation_id: this.currentConversation.id,
                content: content
            });
            
            input.value = '';
            this.renderMessages(response.messages);
            this.scrollMessagesToBottom();
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    showNewConversation() {
        const convList = document.querySelector('.conversation-list');
        const msgView = document.querySelector('.message-view');
        const newConv = document.querySelector('.new-conversation');
        
        if (convList && msgView && newConv) {
            convList.style.display = 'none';
            msgView.style.display = 'none';
            newConv.style.display = 'block';
            this.loadUsersForNewConversation();
        }
    }
    
    async loadUsersForNewConversation() {
        try {
            const response = await fetch('get_users.php');
            const users = await response.json();
            this.renderUserList(users);
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }
    
    renderUserList(users) {
        const container = document.querySelector('.user-list');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (users.length === 0) {
            container.innerHTML = '<div class="no-users-found">No users available</div>';
            return;
        }
        
        users.forEach(user => {
            if (user.id == this.currentUserId) return;
            
            const userEl = document.createElement('div');
            userEl.className = 'user-item';
            userEl.innerHTML = `
                <div class="user-info" onclick="window.messagingWidget.startConversation(${user.id}, '${this.escapeHtml(user.username)}')">
                    <div class="user-name">${this.escapeHtml(user.username)}</div>
                    <div class="user-role">${this.escapeHtml(user.role)}</div>
                </div>
            `;
            container.appendChild(userEl);
        });
    }
    
    filterUserList(searchTerm) {
        const userItems = document.querySelectorAll('.user-item');
        let hasVisibleItems = false;
        
        userItems.forEach(item => {
            const userName = item.querySelector('.user-name').textContent.toLowerCase();
            if (userName.includes(searchTerm.toLowerCase())) {
                item.style.display = 'block';
                hasVisibleItems = true;
            } else {
                item.style.display = 'none';
            }
        });
        
        const noUsersMsg = document.querySelector('.no-users-found') || 
                          document.createElement('div');
        if (!document.querySelector('.no-users-found')) {
            noUsersMsg.className = 'no-users-found';
            noUsersMsg.textContent = 'No users found matching your search';
            document.querySelector('.user-list').appendChild(noUsersMsg);
        }
        
        noUsersMsg.style.display = hasVisibleItems ? 'none' : 'block';
    }
    
    async startConversation(userId, userName) {
        try {
            const response = await this.fetchData('start_conversation', {
                user_id: userId
            });
            
            this.currentConversation = {
                id: response.conversation_id,
                userId: userId,
                userName: userName
            };
            
            this.renderMessages(response.messages);
            
            // Update UI to show message view
            document.querySelector('.conversation-list').style.display = 'none';
            document.querySelector('.new-conversation').style.display = 'none';
            document.querySelector('.message-view').style.display = 'block';
            document.querySelector('.conversation-title').textContent = userName;
            
            // Scroll to bottom of messages
            this.scrollMessagesToBottom();
        } catch (error) {
            console.error('Error starting conversation:', error);
        }
    }
    
    showConversationList() {
        this.loadConversations();
    }
    
    async checkNewMessages() {
        if (!this.widgetOpen) {
            try {
                const response = await this.fetchData('get_conversations');
                this.updateUnreadCount(response.conversations);
            } catch (error) {
                console.error('Error checking new messages:', error);
            }
        }
    }
    
    updateUnreadCount(conversations) {
        this.unreadCount = conversations.reduce((count, conv) => count + (conv.unread_count || 0), 0);
        const badges = document.querySelectorAll('.unread-badge');
        badges.forEach(badge => {
            if (badge) {
                badge.textContent = this.unreadCount > 0 ? this.unreadCount : '';
                badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
            }
        });
    }
    
    formatTime(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    async fetchData(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        const response = await fetch('messaging.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return await response.json();
    }
}

// Initialize the widget when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.messagingWidget = new MessagingWidget();
});

// Make the widget accessible for inline event handlers
window.MessagingWidget = MessagingWidget;