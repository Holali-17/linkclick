// Gestion de l'authentification LinkClick
class AuthManager {
  constructor() {
    this.currentUser = null
    this.token = null
    this.init()
  }

  init() {
    // Vérifier si l'utilisateur est déjà connecté
    this.checkStoredAuth()

    // Écouter les changements de sessionStorage
    window.addEventListener("storage", (e) => {
      if (e.key === "linkclick_token" || e.key === "linkclick_user") {
        this.checkStoredAuth()
      }
    })
  }

checkStoredAuth() {
  const token = sessionStorage.getItem("linkclick_token")
  const userData = sessionStorage.getItem("linkclick_user")

  if (token && userData) {
    this.token = token
    this.currentUser = JSON.parse(userData)

    // Ne pas appeler API si elle est undefined
    if (window.API && typeof window.API.setToken === "function") {
      window.API.setToken(token)
    }

    return true
  }

  return false
}


  async login(email, password) {
    try {
      const response = await window.API.login({ email, password })

      if (response.success) {
        this.token = response.token
        this.currentUser = response.user

        // Stocker dans sessionStorage
        sessionStorage.setItem("linkclick_token", this.token)
        sessionStorage.setItem("linkclick_user", JSON.stringify(this.currentUser))

        window.API.setToken(this.token)

        return { success: true, user: this.currentUser }
      } else {
        return { success: false, message: response.message }
      }
    } catch (error) {
      return { success: false, message: "Erreur de connexion" }
    }
  }

  async register(userData) {
    try {
      const response = await window.API.register(userData)
      return response
    } catch (error) {
      return { success: false, message: "Erreur lors de l'inscription" }
    }
  }

  async forgotPassword(email) {
    try {
      const response = await window.API.forgotPassword(email)
      return response
    } catch (error) {
      return { success: false, message: "Erreur lors de l'envoi de l'email" }
    }
  }

  logout() {
    this.token = null
    this.currentUser = null

    // Supprimer du sessionStorage
    sessionStorage.removeItem("linkclick_token")
    sessionStorage.removeItem("linkclick_user")

    window.API.clearToken()

    // Rediriger vers la page de connexion
    if (typeof window.app !== "undefined") {
      window.app.showAuthPage()
    }
  }

  isAuthenticated() {
    return this.token !== null && this.currentUser !== null
  }

  getCurrentUser() {
    return this.currentUser
  }

  getToken() {
    return this.token
  }

  // Vérifier si l'utilisateur a un rôle spécifique
  hasRole(role) {
    return this.currentUser && this.currentUser.role === role
  }

  // Vérifier si l'utilisateur est admin
  isAdmin() {
    return this.hasRole("admin")
  }

  // Vérifier si l'utilisateur est modérateur
  isModerator() {
    return this.hasRole("moderator") || this.isAdmin()
  }

  // Mettre à jour les informations utilisateur
  updateUser(userData) {
    if (this.currentUser) {
      this.currentUser = { ...this.currentUser, ...userData }
      sessionStorage.setItem("linkclick_user", JSON.stringify(this.currentUser))
    }
  }

  // Validation des données
  validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  validatePassword(password) {
    // Au moins 8 caractères, une majuscule, une minuscule, un chiffre
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/
    return passwordRegex.test(password)
  }

  validateName(name) {
    return name && name.trim().length >= 2
  }

  // Gestion des erreurs d'authentification
  handleAuthError(error) {
    console.error("Erreur d'authentification:", error)

    if (error.status === 401) {
      // Token expiré ou invalide
      this.logout()
      return "Session expirée, veuillez vous reconnecter"
    }

    return error.message || "Erreur d'authentification"
  }
}

// Instance globale du gestionnaire d'authentification
const Auth = new AuthManager()
