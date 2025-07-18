class APIClient {
  constructor() {
    //is.baseURL = "https://link-click.loca.lt/api/";

    this.baseURL = "https://linkclick.onrender.com/api/";
    this.token = sessionStorage.getItem("linkclick_token");
  }

  async request(endpoint, options = {}) {
    const url = this.baseURL + endpoint;
    const config = {
      headers: {
        "Content-Type": "application/json",
        ...options.headers,
      },
      ...options,
    };

    if (this.token) {
      config.headers["Authorization"] = `Bearer ${this.token}`;
    }

    if (options.body instanceof FormData) {
      delete config.headers["Content-Type"];
    }

    try {
      const response = await fetch(url, config);
      const contentType = response.headers.get("content-type");

      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        throw new Error(`Expected JSON, but received ${contentType || "unknown content type"}: ${text.slice(0, 100)}...`);
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || `HTTP error ${response.status}`);
      }

      return data;
    } catch (error) {
      console.error(`API Error for ${url}:`, error);
      throw error;
    }
  }

  // Authentification
  async login(credentials) {
    return this.request("auth/login.php", {
      method: "POST",
      body: JSON.stringify(credentials),
    });
  }

  async register(userData) {
    return this.request("auth/register.php", {
      method: "POST",
      body: JSON.stringify(userData),
    });
  }

  async forgotPassword(email) {
    return this.request("auth/forgot-password.php", {
      method: "POST",
      body: JSON.stringify({ email }),
    });
  }

  async resetPassword(token, password) {
    return this.request("auth/reset-password.php", {
      method: "POST",
      body: JSON.stringify({ token, password }),
    });
  }

  // Posts
  async getPosts() {
    return this.request("posts/get-posts.php");
  }

  async createPost(formData) {
    return this.request("posts/create-post.php", {
      method: "POST",
      body: formData,
    });
  }

  async toggleLike(postId, type) {
    return this.request("posts/toggle-like.php", {
      method: "POST",
      body: JSON.stringify({ post_id: postId, type }),
    });
  }

  async getComments(postId) {
    return this.request(`posts/get-comments.php?post_id=${postId}`);
  }

  async addComment(postId, content) {
    return this.request("posts/add-comment.php", {
      method: "POST",
      body: JSON.stringify({ post_id: postId, content }),
    });
  }

  // Amis
  async getFriends() {
    return this.request("friends/get-friends.php");
  }

  async getFriendRequests() {
    return this.request("friends/get-requests.php");
  }

  async getFriendSuggestions() {
    return this.request("friends/get-suggestions.php");
  }

  async sendFriendRequest(userId) {
    return this.request("friends/send-request.php", {
      method: "POST",
      body: JSON.stringify({ user_id: userId }),
    });
  }

  async acceptFriendRequest(userId) {
    return this.request("friends/accept-request.php", {
      method: "POST",
      body: JSON.stringify({ user_id: userId }),
    });
  }

  async rejectFriendRequest(userId) {
    return this.request("friends/reject-request.php", {
      method: "POST",
      body: JSON.stringify({ user_id: userId }),
    });
  }

  // Chat
  async getConversations() {
    return this.request("chat/get-conversations.php");
  }

  async getMessages(userId) {
    return this.request(`chat/get-messages.php?user_id=${userId}`);
  }

  async sendMessage(userId, content) {
    return this.request("chat/send-message.php", {
      method: "POST",
      body: JSON.stringify({ user_id: userId, content }),
    });
  }

  // Profil
  async getProfile() {
    return this.request("profile/get-profile.php");
  }

  async updateProfile(formData) {
    return this.request("profile/update-profile.php", {
      method: "POST",
      body: formData,
    });
  }

  async getUserInfo(userId) {
    return this.request(`profile/get-user.php?user_id=${userId}`);
  }

  // Recherche
  async searchUsers(query) {
    return this.request(`search/users.php?q=${encodeURIComponent(query)}`);
  }

  // Mettre à jour le token
  setToken(token) {
    this.token = token;
    sessionStorage.setItem("linkclick_token", token);
  }

  // Supprimer le token
  clearToken() {
    this.token = null;
    sessionStorage.removeItem("linkclick_token");
  }
}

// Instance globale de l'API
const API = new APIClient();