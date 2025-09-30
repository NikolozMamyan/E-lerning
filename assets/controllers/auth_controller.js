import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['email', 'password', 'userName', 'accountType', 'result', 'button']

  connect() {

  }

  async login(event) {
    event.preventDefault()

    const email = this.emailTarget.value
    const password = this.passwordTarget.value

    const response = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
      credentials: 'include'
    })

    if (response.ok) {
      const data = await response.json()

      this.resultTarget.innerHTML = `
        <div style="color: var(--health-color);">✔ Login successful... redirecting...</div>
        <span class="spinner"></span>
      `

      // Récupère les rôles de l’utilisateur
      const roles = data.user.roles || []
      let redirectUrl = '/app/dashboard' // par défaut

      if (roles.includes('ROLE_ADMIN')) {
        redirectUrl = '/admin/courses'
      } else if (roles.includes('ROLE_EMPLOYEE')) {
        redirectUrl = '/app/dashboard'
      } else if (roles.includes('ROLE_COMPANY')) {
        redirectUrl = '/company/dashboard'
      }

      setTimeout(() => {
        window.location.href = redirectUrl
      }, 1200)

    } else {
      const error = await safeJson(response)
      this.resultTarget.innerHTML = `
        <div style="color: var(--damage-color);">
          ⚠ ${error?.error || 'Login failed'}
        </div>
      `
    }
  }

  async logout(event) {
    event.preventDefault()

    try {
      const response = await fetch("/api/logout", {
        method: "POST",
        credentials: "include", // important pour envoyer les cookies
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })

      if (!response.ok) {
        const error = await response.json()
        console.error("Erreur de déconnexion :", error.error)
        return
      }

      const data = await response.json()
      console.log(data.message) // "Déconnexion réussie"

      window.location.href = "/login"
    } catch (err) {
      console.error("Erreur réseau :", err)
    }
  }

  async register(event) {
    event.preventDefault()

    const email = this.emailTarget.value.trim()
    const password = this.passwordTarget.value
    const userName = this.userNameTarget.value.trim()
    const selectedAccountType =
      this.accountTypeTargets.find(radio => radio.checked)?.value || "employee"

    if (!email || !password || !userName) {
      this.resultTarget.innerHTML = `
        <div style="color: var(--damage-color);">
          ⚠ Please fill in all fields.
        </div>
      `
      return
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content

    try {
      this.resultTarget.innerHTML = `
        <span class="spinner"></span> Creating your account…
      `

      const response = await fetch("/api/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...(csrf ? { "X-CSRF-Token": csrf } : {})
        },
        body: JSON.stringify({
          email,
          password,
          userName,
          role: selectedAccountType
        })
      })

      if (response.ok) {
        const data = await response.json()

        this.resultTarget.innerHTML = `
          <div style="color: var(--health-color);">✔ Account created! Redirecting…</div>
          <span class="spinner"></span>
        `

        // Redirection selon rôle
        const roles = data.user?.roles || [selectedAccountType.toUpperCase()]
        let redirectUrl = '/app/dashboard'

        if (roles.includes('ROLE_ADMIN')) {
          redirectUrl = '/admin/courses'
        } else if (roles.includes('ROLE_EMPLOYEE')) {
          redirectUrl = '/app/dashboard'
        } else if (roles.includes('ROLE_COMPANY')) {
          redirectUrl = '/company/dashboard'
        }

        setTimeout(() => {
          window.location.href = redirectUrl
        }, 1500)
      } else {
        const errorData = await safeJson(response)
        this.resultTarget.innerHTML = `
          <div style="color: var(--damage-color);">
            ⚠ ${errorData?.error || errorData?.message || "Something went wrong."}
          </div>
        `
      }
    } catch (e) {
      this.resultTarget.innerHTML = `
        <div style="color: var(--damage-color);">
          ⚠ Network error, please try again.
        </div>
      `
      console.error(e)
    }
  }
}

// Helper safe JSON
async function safeJson(res) {
  try { return await res.json() } catch { return null }
}
