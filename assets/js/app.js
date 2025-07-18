class LinkClickApp {
  constructor() {
    this.currentUser = null
    this.currentPage = "home"
    this.chatInterval = null
    this.API = API // Declare the API variable
    this.init()
  }

  async init() {
    // Vérifier l'authentification
    await this.checkAuth()

    // Initialiser les événements
    this.initEventListeners()

    // Masquer le loading
    setTimeout(() => {
      document.getElementById("loading").style.display = "none"
      document.getElementById("main-content").style.display = "block"
    }, 2000)
  }

  async checkAuth() {
    const token = sessionStorage.getItem("linkclick_token")
    const userData = sessionStorage.getItem("linkclick_user")

    if (token && userData) {
      this.currentUser = JSON.parse(userData)
      this.showMainApp()
    } else {
      this.showAuthPage()
    }
  }

  showMainApp() {
    document.getElementById("navbar").style.display = "block"
    this.loadPage("home")
    this.startChatPolling()
  }

  showAuthPage() {
    document.getElementById("navbar").style.display = "none"
    this.loadPage("login")
  }

  initEventListeners() {
    // Navigation
    document.addEventListener("click", (e) => {
      if (e.target.matches(".nav-item[data-page]")) {
        e.preventDefault()
        const page = e.target.getAttribute("data-page")
        this.loadPage(page)
      }
    })

    // Déconnexion
    document.getElementById("logout-btn")?.addEventListener("click", (e) => {
      e.preventDefault()
      this.logout()
    })

    // Recherche
    document.getElementById("search-input")?.addEventListener("input", (e) => {
      this.searchUsers(e.target.value)
    })
  }

  initPageEvents(pageName) {
  switch (pageName) {
    case "login":
      // Événements pour la page de connexion
      const loginForm = document.getElementById("login-form");
      if (loginForm) {
        loginForm.addEventListener("submit", async (e) => {
          e.preventDefault();
          const email = loginForm.querySelector("#email").value;
          const password = loginForm.querySelector("#password").value;
          try {
            const response = await this.API.login({ email, password });
            if (response.success) {
              this.currentUser = response.user;
              sessionStorage.setItem("linkclick_user", JSON.stringify(response.user));
              this.API.setToken(response.token);
              this.showMainApp();
              this.showNotification("Connexion réussie", "success");
            }
          } catch (error) {
            this.showNotification("Erreur de connexion: " + error.message, "error");
          }
        });
      }

      const showRegisterLink = document.getElementById("show-register");
      if (showRegisterLink) {
        showRegisterLink.addEventListener("click", (e) => {
          e.preventDefault();
          this.loadPage("register");
        });
      }

      const forgotPasswordLink = document.getElementById("forgot-password");
      if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener("click", (e) => {
          e.preventDefault();
          this.showNotification("Fonctionnalité de réinitialisation du mot de passe non implémentée", "info");
        });
      }
      break;

    case "register":
      // Événements pour la page d'inscription
      const registerForm = document.getElementById("register-form");
      if (registerForm) {
        registerForm.addEventListener("submit", async (e) => {
          e.preventDefault();
          const userData = {
            firstname: registerForm.querySelector("#firstname").value,
            lastname: registerForm.querySelector("#lastname").value,
            email: registerForm.querySelector("#email").value,
            password: registerForm.querySelector("#password").value,
            confirm_password: registerForm.querySelector("#confirm_password").value,
          };
          if (userData.password !== userData.confirm_password) {
            this.showNotification("Les mots de passe ne correspondent pas", "error");
            return;
          }
          try {
            const response = await this.API.register(userData);
            if (response.success) {
              this.showNotification("Inscription réussie ! Veuillez vous connecter.", "success");
              this.loadPage("login");
            }
          } catch (error) {
            this.showNotification("Erreur d'inscription: " + error.message, "error");
          }
        });
      }

      const showLoginLink = document.getElementById("show-login");
      if (showLoginLink) {
        showLoginLink.addEventListener("click", (e) => {
          e.preventDefault();
          this.loadPage("login");
        });
      }
      break;

    case "home":
      // Événements pour la page d'accueil
      const postForm = document.getElementById("post-form");
      if (postForm) {
        postForm.addEventListener("submit", async (e) => {
          e.preventDefault();
          const formData = new FormData();
          formData.append("content", postForm.querySelector("textarea[name='content']").value);
          const imageInput = postForm.querySelector("#post-image");
          if (imageInput.files[0]) {
            formData.append("image", imageInput.files[0]);
          }
          try {
            const response = await this.API.createPost(formData);
            if (response.success) {
              postForm.reset();
              this.loadPage("home");
              this.showNotification("Publication créée avec succès", "success");
            }
          } catch (error) {
            this.showNotification("Erreur lors de la création de la publication: " + error.message, "error");
          }
        });
      }

      // Événements pour les actions des publications (like, dislike, commentaire)
      document.querySelectorAll(".post-action").forEach((button) => {
        button.addEventListener("click", async (e) => {
          const postId = e.target.closest(".post").getAttribute("data-post-id");
          const action = e.target.getAttribute("data-action");
          if (action === "like" || action === "dislike") {
            try {
              await this.API.toggleLike(postId, action);
              this.loadPage("home");
            } catch (error) {
              this.showNotification("Erreur lors de l'action: " + error.message, "error");
            }
          } else if (action === "comment") {
            const commentsSection = document.getElementById(`comments-${postId}`);
            commentsSection.classList.toggle("hidden");
          }
        });
      });
      break;

    case "friends":
      // Événements pour la page des amis
      document.querySelectorAll(".tab-btn").forEach((tab) => {
        tab.addEventListener("click", (e) => {
          const tabName = e.target.getAttribute("data-tab");
          document.querySelectorAll(".tab-btn").forEach((btn) => btn.classList.remove("active"));
          document.querySelectorAll(".tab-content").forEach((content) => content.classList.add("hidden"));
          e.target.classList.add("active");
          document.getElementById(`${tabName}-tab`).classList.remove("hidden");
        });
      });
      break;

    case "chat":
      // Événements pour la page de chat
      const chatSearch = document.getElementById("chat-search");
      if (chatSearch) {
        chatSearch.addEventListener("input", (e) => {
          this.filterChats(e.target.value);
        });
      }
      break;

    case "profile":
      // Événements pour la page de profil
      const profileEditForm = document.getElementById("profile-edit-form");
      if (profileEditForm) {
        profileEditForm.addEventListener("submit", async (e) => {
          e.preventDefault();
          const formData = new FormData(profileEditForm);
          try {
            const response = await this.API.updateProfile(formData);
            if (response.success) {
              this.showNotification("Profil mis à jour avec succès", "success");
              this.loadPage("profile");
            }
          } catch (error) {
            this.showNotification("Erreur lors de la mise à jour du profil: " + error.message, "error");
          }
        });
      }
      break;

    default:
      console.log(`Aucun événement spécifique défini pour la page: ${pageName}`);
  }
}

async getProfilePage() {
  try {
    //console.log("Token envoyé:", sessionStorage.getItem("linkclick_token"));
    const response = await this.API.getProfile();
    
    // Vérifier si la réponse est valide et contient les données du profil
    if (!response.success || !response.profile) {
      throw new Error(response.message || "Erreur lors du chargement du profil");
    }

    const profile = response.profile;

    return `
      <div class="profile-container fade-in">
        <div class="profile-header">
          <img style="width: 65px; height: 65px;" src="../../api/${profile.avatar || '/assets/uploads/image.png'}" alt="Avatar" class="profile-avatar">
          <h2>${profile.firstname} ${profile.lastname}</h2>
          <p class="profile-status">${profile.bio || 'Aucune bio définie'}</p>
          <button class="btn btn-secondary" onclick="app.toggleProfileEdit()">Modifier le profil</button>
        </div>
        <form id="profile-edit-form" class="hidden">
          <div class="form-group">
            <label class="form-label" for="firstname">Prénom</label>
            <input type="text" id="firstname" name="firstname" value="${profile.firstname}" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="lastname">Nom</label>
            <input type="text" id="lastname" name="lastname" value="${profile.lastname}" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="bio">Bio</label>
            <textarea id="bio" name="bio" class="form-textarea">${profile.bio || ''}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label" for="avatar">Avatar</label>
            <input type="file" id="avatar" name="avatar" accept="image/*">
          </div>
          <button type="submit" class="btn">Enregistrer</button>
        </form>
        <div class="profile-stats">
          <div class="stat">
            <span class="stat-value">${profile.posts_count || 0}</span>
            <p>Publications</p>
          </div>
          <div class="stat">
            <span class="stat-value">${profile.friends_count || 0}</span>
            <p>Amis</p>
          </div>
        </div>
      </div>
    `;
  } catch (error) {
    console.error("Erreur API:", error);
    this.showNotification("Erreur lors du chargement du profil: " + error.message, "error");
    return `
      <div class="profile-container fade-in">
        <div class="text-center">
          <h2>Erreur lors du chargement du profil</h2>
          <p>${error.message}</p>
        </div>
      </div>
    `;
  }
}

  async loadPage(pageName) {
    const container = document.getElementById("page-container")

    // Mettre à jour la navigation active
    document.querySelectorAll(".nav-item").forEach((item) => {
      item.classList.remove("active")
    })
    document.querySelector(`[data-page="${pageName}"]`)?.classList.add("active")

    try {
      let content = ""

      switch (pageName) {
        case "login":
          content = await this.getLoginPage()
          break
        case "register":
          content = await this.getRegisterPage()
          break
        case "home":
          content = await this.getHomePage()
          break
        case "friends":
          content = await this.getFriendsPage()
          break
        case "chat":
          content = await this.getChatPage()
          break
        case "profile":
          content = await this.getProfilePage()
          break
        default:
          content = '<div class="text-center"><h2>Page non trouvée</h2></div>'
      }

      container.innerHTML = content
      this.currentPage = pageName

      // Initialiser les événements spécifiques à la page
      this.initPageEvents(pageName)
    } catch (error) {
      console.error("Erreur lors du chargement de la page:", error)
      this.showNotification("Erreur lors du chargement de la page", "error")
    }
  }

  async getLoginPage() {
    return `
            <div class="auth-container">
                <div class="auth-card fade-in">
                    <div class="auth-header">
                        <h1><i class="fas fa-link"></i> LinkClick</h1>
                        <p>Connectez-vous à votre réseau</p>
                    </div>
                    <form id="login-form">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-full">Se connecter</button>
                    </form>
                    <div class="auth-links">
                        <p><a href="#" id="forgot-password">Mot de passe oublié ?</a></p>
                        <p>Pas encore de compte ? <a href="#" id="show-register">S'inscrire</a></p>
                    </div>
                </div>
            </div>
        `
  }

  async addComment(postId, content) {
  if (!content.trim()) {
    this.showNotification("Le commentaire ne peut pas être vide", "error");
    return;
  }

  try {
    const response = await this.API.addComment(postId, content);
    if (response.success) {
      this.showNotification("Commentaire ajouté avec succès", "success");
      // Recharger la page d'accueil pour afficher le nouveau commentaire
      this.loadPage("home");
    } else {
      throw new Error(response.message || "Erreur lors de l'ajout du commentaire");
    }
  } catch (error) {
    this.showNotification("Erreur lors de l'ajout du commentaire : " + error.message, "error");
  }
}

  async getRegisterPage() {
    return `
            <div class="auth-container">
                <div class="auth-card fade-in">
                    <div class="auth-header">
                        <h1><i class="fas fa-link"></i> LinkClick</h1>
                        <p>Rejoignez notre communauté</p>
                    </div>
                    <form id="register-form">
                        <div class="form-group">
                            <label for="firstname">Prénom</label>
                            <input type="text" id="firstname" name="firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="lastname">Nom</label>
                            <input type="text" id="lastname" name="lastname" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-full">S'inscrire</button>
                    </form>
                    <div class="auth-links">
                        <p>Déjà un compte ? <a href="#" id="show-login">Se connecter</a></p>
                    </div>
                </div>
            </div>
        `
  }

async getHomePage() {
  try {
    const response = await this.API.getPosts();
    
    // Vérifier si la réponse est valide et contient un tableau de posts
    if (!response.success || !Array.isArray(response.posts)) {
      throw new Error(response.message || "Aucune publication trouvée");
    }

    const posts = response.posts;

    return `
      <div class="home-container fade-in">
        <div class="post-form">
          <form id="post-form">
            <textarea placeholder="Quoi de neuf, ${this.currentUser.firstname} ?" name="content" required></textarea>
            <div class="post-form-actions">
              <div class="file-input-wrapper">
                <input type="file" id="post-image" name="image" accept="image/*">
                <label for="post-image" class="file-input-label">
                  <i class="fas fa-image"></i>
                  <span>Ajouter une image</span>
                </label>
              </div>
              <button type="submit" class="btn">Publier</button>
            </div>
          </form>
        </div>
        <div class="posts-container" id="posts-container">
          ${posts.length > 0 
            ? posts.map((post) => this.renderPost(post)).join("")
            : "<p class='text-center'>Aucune publication disponible.</p>"}
        </div>
      </div>
    `;
  } catch (error) {
    this.showNotification("Erreur lors du chargement des publications: " + error.message, "error");
    return `
      <div class="home-container fade-in">
        <div class="post-form">
          <form id="post-form">
            <textarea placeholder="Quoi de neuf, ${this.currentUser.firstname} ?" name="content" required></textarea>
            <div class="post-form-actions">
              <div class="file-input-wrapper">
                <input type="file" id="post-image" name="image" accept="image/*">
                <label for="post-image" class="file-input-label">
                  <i class="fas fa-image"></i>
                  <span>Ajouter une image</span>
                </label>
              </div>
              <button type="submit" class="btn">Publier</button>
            </div>
          </form>
        </div>
        <div class="posts-container" id="posts-container">
          <p class="text-center">Erreur lors du chargement des publications.</p>
        </div>
      </div>
    `;
  }
}

  renderPost(post) {
    return `
            <div class="post fade-in" data-post-id="${post.id}">
                <div class="post-header">
                    <img src="../../api/${post.user_avatar || "/assets/uploads/image.png"}" alt="Avatar" class="post-avatar">
                    <div class="post-user-info">
                        <h4>${post.user_name}</h4>
                        <span>${this.formatDate(post.created_at)}</span>
                    </div>
                </div>
                <div class="post-content">
                    <p>${post.content}</p>
                    ${post.image ? `<img src="../../api/${post.image}" alt="Post image" class="post-image">` : ""}
                </div>
                <div class="post-actions">
                    <button class="post-action ${post.user_liked ? "liked" : ""}" data-action="like">
                        <i class="fas fa-thumbs-up"></i>
                        <span>${post.likes_count || 0}</span>
                    </button>
                    <button class="post-action ${post.user_disliked ? "disliked" : ""}" data-action="dislike">
                        <i class="fas fa-thumbs-down"></i>
                        <span>${post.dislikes_count || 0}</span>
                    </button>
                    <button class="post-action" data-action="comment">
                        <i class="fas fa-comment"></i>
                        <span>Commenter</span>
                    </button>
                </div>
                <div class="comments-section hidden" id="comments-${post.id}">
                    <div class="comment-form">
                        <input type="text" placeholder="Écrivez un commentaire..." data-post-id="${post.id}">
                        <button type="button" onclick="app.addComment(${post.id}, this.previousElementSibling.value)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="comments-list" id="comments-list-${post.id}">
                        ${post.comments ? post.comments.map((comment) => this.renderComment(comment)).join("") : ""}
                    </div>
                </div>
            </div>
        `
  }

  renderComment(comment) {
    return `
            <div class="comment">
                <img src="../../api/${comment.user_avatar || "/assets/uploads/image.png"}" alt="Avatar" class="comment-avatar">
                <div class="comment-content">
                    <div class="comment-author">${comment.user_name}</div>
                    <div class="comment-text">${comment.content}</div>
                </div>
            </div>
        `
  }

async getFriendsPage() {
  const friendsResponse = await this.API.getFriends()
  const requestsResponse = await this.API.getFriendRequests()
  const suggestionsResponse = await this.API.getFriendSuggestions()

  const friends = Array.isArray(friendsResponse.friends) ? friendsResponse.friends : []
  const requests = Array.isArray(requestsResponse.requests) ? requestsResponse.requests : []
  const suggestions = Array.isArray(suggestionsResponse.suggestions) ? suggestionsResponse.suggestions : []

  return `
    <div class="friends-container fade-in">
      <div class="friends-tabs">
        <button class="tab-btn active" data-tab="friends">Mes amis (${friends.length})</button>
        <button class="tab-btn" data-tab="requests">Demandes (${requests.length})</button>
        <button class="tab-btn" data-tab="suggestions">Suggestions</button>
      </div>
      
      <div class="tab-content" id="friends-tab">
        <div class="friends-grid">
          ${friends.map((friend) => this.renderFriendCard(friend, "friend")).join("")}
        </div>
      </div>
      
      <div class="tab-content hidden" id="requests-tab">
        <div class="friends-grid">
          ${requests.map((request) => this.renderFriendCard(request, "request")).join("")}
        </div>
      </div>
      
      <div class="tab-content hidden" id="suggestions-tab">
        <div class="friends-grid">
          ${suggestions.map((suggestion) => this.renderFriendCard(suggestion, "suggestion")).join("")}
        </div>
      </div>
    </div>
  `
}


  renderFriendCard(user, type) {
    let actions = ""

    switch (type) {
      case "friend":
        actions = `
                    <div class="friend-actions">
                        <button class="btn btn-sm" onclick="app.viewProfile(${user.id})">Voir profil</button>
                        <button class="btn btn-sm btn-secondary" onclick="app.startChat(${user.id})">Message</button>
                    </div>
                `
        break
      case "request":
        actions = `
                    <div class="friend-actions">
                        <button class="btn btn-sm btn-success" onclick="app.acceptFriendRequest(${user.id})">Accepter</button>
                        <button class="btn btn-sm btn-danger" onclick="app.rejectFriendRequest(${user.id})">Refuser</button>
                    </div>
                `
        break
      case "suggestion":
        actions = `
                    <div class="friend-actions">
                        <button class="btn btn-sm" onclick="app.sendFriendRequest(${user.id})">Ajouter</button>
                        <button class="btn btn-sm btn-secondary" onclick="app.viewProfile(${user.id})">Voir profil</button>
                    </div>
                `
        break
    }

    return `
            <div class="friend-card">
                <img src="../../api/${user.avatar || "/assets/uploads/image.png"}" alt="Avatar" class="friend-avatar">
                <div class="friend-name">${user.firstname} ${user.lastname}</div>
                <div class="friend-status">${user.status || "Membre LinkClick"}</div>
                ${actions}
            </div>
        `
  }

  renderMessage(msg) {
  return `
    <div class="message ${msg.from_current_user ? 'sent' : 'received'}">
      <div class="message-content">${msg.content}</div>
      <div class="message-time">${this.formatTime(msg.created_at)} par ${msg.from_current_user ? 'vous' : msg.sender_name}</div>
    </div>
  `
}


async getChatPage() {
  try {
    const response = await this.API.getConversations();
    
    // Vérifier si la réponse est valide et contient un tableau de conversations
    if (!response.success || !Array.isArray(response.conversations)) {
      throw new Error(response.message || "Aucune conversation trouvée");
    }

    const conversations = response.conversations;

    return `
      <div class="chat-container fade-in">
        <div class="chat-sidebar">
          <div class="chat-search">
            <input type="text" placeholder="Rechercher une conversation..." id="chat-search">
          </div>
          <div class="chat-list" id="chat-list">
            ${conversations.length > 0 
              ? conversations.map((conv) => this.renderChatItem(conv)).join("")
              : "<p class='text-center'>Aucune conversation disponible.</p>"}
          </div>
        </div>
        <div class="chat-main" id="chat-main">
          <div class="flex-center" style="height: 100%; color: var(--gray);">
            <div class="text-center">
              <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 1rem;"></i>
              <p>Sélectionnez une conversation pour commencer</p>
            </div>
          </div>
        </div>
      </div>
    `;
  } catch (error) {
    this.showNotification("Erreur lors du chargement des conversations: " + error.message, "error");
    return `
      <div class="chat-container fade-in">
        <div class="chat-sidebar">
          <div class="chat-search">
            <input type="text" placeholder="Rechercher une conversation..." id="chat-search">
          </div>
          <div class="chat-list" id="chat-list">
            <p class="text-center">Erreur lors du chargement des conversations.</p>
          </div>
        </div>
        <div class="chat-main" id="chat-main">
          <div class="flex-center" style="height: 100%; color: var(--gray);">
            <div class="text-center">
              <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 1rem;"></i>
              <p>Erreur lors du chargement des conversations</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }
}

  renderChatItem(conversation) {
    return `
            <div class="chat-item" data-user-id="${conversation.user_id}" onclick="app.openChat(${conversation.user_id})">
                <img src="../../api/${conversation.user_avatar || "/assets/uploads/image.png"}" alt="Avatar" class="chat-item-avatar">
                <div class="chat-item-info">
                    <div class="chat-item-name">${conversation.user_name}</div>
                    <div class="chat-item-last">${conversation.last_message || "Aucun message"}</div>
                </div>
            </div>
        `
  }

  
  async sendFriendRequest(userId) {
    try {
      const response = await this.API.sendFriendRequest(userId);
      if (response.success) {
        this.showNotification("Demande d'ami envoyée", "success");
        this.loadPage("friends");
      }
    } catch (error) {
      this.showNotification("Erreur lors de l'envoi de la demande: " + error.message, "error");
    }
  }

  async viewProfile(userId) {
    try {
      const user = await this.API.getUserInfo(userId);
      if (user.success) {
        // Exemple simple : afficher un modal ou naviguer vers une page profil
        alert(`Profil de ${user.user.firstname} ${user.user.lastname}`);
        // Ou tu peux faire un loadPage("profile") avec l'utilisateur chargé
      }
    } catch (error) {
      this.showNotification("Erreur lors du chargement du profil: " + error.message, "error");
    }
  }

  async acceptFriendRequest(userId) {
    try {
      const response = await this.API.acceptFriendRequest(userId);
      if (response.success) {
        this.showNotification("Demande d'ami acceptée", "success");
        this.loadPage("friends");
      }
    } catch (error) {
      this.showNotification("Erreur lors de l'acceptation: " + error.message, "error");
    }
  }

  async rejectFriendRequest(userId) {
    try {
      const response = await this.API.rejectFriendRequest(userId);
      if (response.success) {
        this.showNotification("Demande d'ami refusée", "info");
        this.loadPage("friends");
      }
    } catch (error) {
      this.showNotification("Erreur lors du refus: " + error.message, "error");
    }
  }

  async startChat(userId) {
    // Cette méthode peut juste appeler loadPage("chat") et ouvrir la conversation
    this.loadPage("chat").then(() => {
      this.openChat(userId);
    });
  }

  async openChat(userId) {
  try {
    const response = await this.API.getMessages(userId)

    if (!response.success || !Array.isArray(response.messages)) {
      throw new Error(response.message || "Impossible de charger les messages.")
    }

    const messages = response.messages

    const chatMain = document.getElementById("chat-main")
    if (chatMain) {
      chatMain.innerHTML = `
        <div class="chat-header">
          <button class="btn btn-sm btn-secondary" onclick="app.loadPage('chat')">← Retour</button>
          <h3>Conversation</h3>
        </div>
        <div class="chat-messages" id="chat-messages">
          ${messages.map((msg) => this.renderMessage(msg)).join("")}
        </div>
        <div class="chat-input">
          <input type="text" id="message-input" placeholder="Écrivez un message...">
          <button onclick="app.sendMessage(${userId})"><i class="fas fa-paper-plane"></i></button>
        </div>
      `
    }
  } catch (error) {
    this.showNotification("Erreur lors du chargement de la conversation: " + error.message, "error")
  }
}


  async sendMessage(userId) {
    const input = document.getElementById("message-input")
    const content = input.value.trim()

    if (!content) return

    try {
      const response = await this.API.sendMessage(userId, content)
      if (response.success) {
        input.value = ""
        this.openChat(userId) // Recharger les messages
      }
    } catch (error) {
      this.showNotification("Erreur lors de l'envoi du message", "error")
    }
  }

  toggleProfileEdit() {
    const form = document.getElementById("profile-edit-form")
    form.classList.toggle("hidden")
  }

  startChatPolling() {
    // Polling toutes les 3 secondes pour les nouveaux messages
    this.chatInterval = setInterval(async () => {
      if (this.currentPage === "chat") {
        const activeChat = document.querySelector(".chat-item.active")
        if (activeChat) {
          const userId = activeChat.getAttribute("data-user-id")
          // Recharger les messages silencieusement
          try {
            const messages = await this.API.getMessages(userId)
            const messagesContainer = document.getElementById("chat-messages")
            if (messagesContainer) {
              const scrollAtBottom =
                messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 10
              messagesContainer.innerHTML = messages.map((msg) => this.renderMessage(msg)).join("")

              if (scrollAtBottom) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight
              }
            }
          } catch (error) {
            // Ignorer les erreurs de polling
          }
        }
      }
    }, 3000)
  }

  logout() {
    sessionStorage.removeItem("linkclick_token")
    sessionStorage.removeItem("linkclick_user")
    this.currentUser = null

    if (this.chatInterval) {
      clearInterval(this.chatInterval)
    }

    this.showAuthPage()
    this.showNotification("Déconnexion réussie", "info")
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `notification ${type}`
    notification.textContent = message

    document.body.appendChild(notification)

    setTimeout(() => {
      notification.remove()
    }, 3000)
  }

  formatDate(dateString) {
    const date = new Date(dateString)
    const now = new Date()
    const diff = now - date

    if (diff < 60000) return "À l'instant"
    if (diff < 3600000) return `${Math.floor(diff / 60000)} min`
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} h`

    return date.toLocaleDateString("fr-FR")
  }

  formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  searchUsers(query) {
    // Implémentation de la recherche d'utilisateurs
    if (query.length > 2) {
      this.API.searchUsers(query).then((users) => {
        // Afficher les résultats de recherche
        console.log("Résultats de recherche:", users)
      })
    }
  }

  filterChats(query) {
    const chatItems = document.querySelectorAll(".chat-item")
    chatItems.forEach((item) => {
      const name = item.querySelector(".chat-item-name").textContent.toLowerCase()
      if (name.includes(query.toLowerCase())) {
        item.style.display = "flex"
      } else {
        item.style.display = "none"
      }
    })
  }
}

// Initialiser l'application
window.app = new LinkClickApp()