// Utilitaires pour LinkClick
class Utils {
  // Formatage des dates
  static formatDate(dateString) {
    const date = new Date(dateString)
    const now = new Date()
    const diff = now - date

    // Moins d'une minute
    if (diff < 60000) {
      return "À l'instant"
    }

    // Moins d'une heure
    if (diff < 3600000) {
      const minutes = Math.floor(diff / 60000)
      return `${minutes} min`
    }

    // Moins d'un jour
    if (diff < 86400000) {
      const hours = Math.floor(diff / 3600000)
      return `${hours} h`
    }

    // Moins d'une semaine
    if (diff < 604800000) {
      const days = Math.floor(diff / 86400000)
      return `${days} j`
    }

    // Plus d'une semaine
    return date.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
      year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
    })
  }

  static formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  static formatDateTime(dateString) {
    const date = new Date(dateString)
    return date.toLocaleString("fr-FR", {
      day: "numeric",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  // Validation des fichiers
  static validateImage(file) {
    const allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"]
    const maxSize = 5 * 1024 * 1024 // 5MB

    if (!allowedTypes.includes(file.type)) {
      return { valid: false, message: "Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP." }
    }

    if (file.size > maxSize) {
      return { valid: false, message: "Le fichier est trop volumineux. Taille maximale : 5MB." }
    }

    return { valid: true }
  }

  // Redimensionnement d'images
  static resizeImage(file, maxWidth = 800, maxHeight = 600, quality = 0.8) {
    return new Promise((resolve) => {
      const canvas = document.createElement("canvas")
      const ctx = canvas.getContext("2d")
      const img = new Image()

      img.onload = () => {
        // Calculer les nouvelles dimensions
        let { width, height } = img

        if (width > height) {
          if (width > maxWidth) {
            height = (height * maxWidth) / width
            width = maxWidth
          }
        } else {
          if (height > maxHeight) {
            width = (width * maxHeight) / height
            height = maxHeight
          }
        }

        canvas.width = width
        canvas.height = height

        // Dessiner l'image redimensionnée
        ctx.drawImage(img, 0, 0, width, height)

        // Convertir en blob
        canvas.toBlob(resolve, file.type, quality)
      }

      img.src = URL.createObjectURL(file)
    })
  }

  // Échapper le HTML
  static escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }

  // Détecter les liens dans le texte
  static linkify(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g
    return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
  }

  // Tronquer le texte
  static truncate(text, length = 100) {
    if (text.length <= length) return text
    return text.substring(0, length) + "..."
  }

  // Générer un ID unique
  static generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2)
  }

  // Debounce pour les recherches
  static debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  // Throttle pour les événements de scroll
  static throttle(func, limit) {
    let inThrottle
    return function () {
      const args = arguments
      
      if (!inThrottle) {
        func.apply(this, args)
        inThrottle = true
        setTimeout(() => (inThrottle = false), limit)
      }
    }
  }

  // Copier du texte dans le presse-papiers
  static async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text)
      return true
    } catch (err) {
      // Fallback pour les navigateurs plus anciens
      const textArea = document.createElement("textarea")
      textArea.value = text
      document.body.appendChild(textArea)
      textArea.focus()
      textArea.select()

      try {
        document.execCommand("copy")
        document.body.removeChild(textArea)
        return true
      } catch (err) {
        document.body.removeChild(textArea)
        return false
      }
    }
  }

  // Détecter si l'utilisateur est sur mobile
  static isMobile() {
    return window.innerWidth <= 768
  }

  // Détecter si l'utilisateur est en ligne
  static isOnline() {
    return navigator.onLine
  }

  // Formater les nombres (ex: 1000 -> 1K)
  static formatNumber(num) {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + "M"
    }
    if (num >= 1000) {
      return (num / 1000).toFixed(1) + "K"
    }
    return num.toString()
  }

  // Générer une couleur aléatoire
  static randomColor() {
    const colors = [
      "#FF6B6B",
      "#4ECDC4",
      "#45B7D1",
      "#96CEB4",
      "#FFEAA7",
      "#DDA0DD",
      "#98D8C8",
      "#F7DC6F",
      "#BB8FCE",
      "#85C1E9",
    ]
    return colors[Math.floor(Math.random() * colors.length)]
  }

  // Générer un avatar par défaut avec les initiales
  static generateAvatar(name, size = 100) {
    const canvas = document.createElement("canvas")
    const ctx = canvas.getContext("2d")

    canvas.width = size
    canvas.height = size

    // Couleur de fond
    ctx.fillStyle = this.randomColor()
    ctx.fillRect(0, 0, size, size)

    // Initiales
    const initials = name
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase()
      .substring(0, 2)
    ctx.fillStyle = "#FFFFFF"
    ctx.font = `${size / 2.5}px Arial`
    ctx.textAlign = "center"
    ctx.textBaseline = "middle"
    ctx.fillText(initials, size / 2, size / 2)

    return canvas.toDataURL()
  }

  // Notification toast
  static showToast(message, type = "info", duration = 3000) {
    const toast = document.createElement("div")
    toast.className = `notification ${type}`
    toast.textContent = message

    // Styles inline pour s'assurer que la notification s'affiche
    toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            animation: slideIn 0.3s ease-out;
            max-width: 300px;
            word-wrap: break-word;
        `

    // Couleurs selon le type
    switch (type) {
      case "success":
        toast.style.backgroundColor = "#42b883"
        break
      case "error":
        toast.style.backgroundColor = "#e74c3c"
        break
      case "warning":
        toast.style.backgroundColor = "#f39c12"
        break
      default:
        toast.style.backgroundColor = "#1877f2"
    }

    document.body.appendChild(toast)

    setTimeout(() => {
      toast.style.animation = "slideOut 0.3s ease-in"
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast)
        }
      }, 300)
    }, duration)
  }

  // Loader/Spinner
  static showLoader(container) {
    const loader = document.createElement("div")
    loader.className = "loader-spinner"
    loader.innerHTML = `
            <div style="
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
            ">
                <div style="
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #1877f2;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                "></div>
            </div>
        `

    if (typeof container === "string") {
      container = document.getElementById(container)
    }

    container.appendChild(loader)
    return loader
  }

  static hideLoader(loader) {
    if (loader && loader.parentNode) {
      loader.parentNode.removeChild(loader)
    }
  }

  // Gestion des erreurs
  static handleError(error, context = "") {
    console.error(`Erreur ${context}:`, error)

    let message = "Une erreur est survenue"

    if (error.message) {
      message = error.message
    } else if (typeof error === "string") {
      message = error
    }

    this.showToast(message, "error")
  }

  // Validation des formulaires
  static validateForm(formElement) {
    const errors = []
    const inputs = formElement.querySelectorAll("input[required], textarea[required], select[required]")

    inputs.forEach((input) => {
      if (!input.value.trim()) {
        errors.push(`Le champ ${input.name || input.id} est requis`)
        input.classList.add("error")
      } else {
        input.classList.remove("error")
      }

      // Validation spécifique par type
      if (input.type === "email" && input.value) {
        const Auth = { validateEmail: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) } // Placeholder for Auth
        if (!Auth.validateEmail(input.value)) {
          errors.push("Adresse email invalide")
          input.classList.add("error")
        }
      }

      if (input.type === "password" && input.value) {
        const Auth = { validatePassword: (password) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/.test(password) } // Placeholder for Auth
        if (!Auth.validatePassword(input.value)) {
          errors.push("Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre")
          input.classList.add("error")
        }
      }
    })

    return {
      isValid: errors.length === 0,
      errors,
    }
  }

  // Gestion du localStorage avec expiration
  static setStorageWithExpiry(key, value, ttl) {
    const now = new Date()
    const item = {
      value: value,
      expiry: now.getTime() + ttl,
    }
    localStorage.setItem(key, JSON.stringify(item))
  }

  static getStorageWithExpiry(key) {
    const itemStr = localStorage.getItem(key)
    if (!itemStr) {
      return null
    }

    const item = JSON.parse(itemStr)
    const now = new Date()

    if (now.getTime() > item.expiry) {
      localStorage.removeItem(key)
      return null
    }

    return item.value
  }
}

// Ajouter les styles CSS pour les animations si ils n'existent pas
if (!document.querySelector("#utils-styles")) {
  const style = document.createElement("style")
  style.id = "utils-styles"
  style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error {
            border-color: #e74c3c !important;
            box-shadow: 0 0 5px rgba(231, 76, 60, 0.3) !important;
        }
    `
  document.head.appendChild(style)
}
