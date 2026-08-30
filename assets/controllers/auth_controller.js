import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'email',
    'password',
    'userName',
    'accountType',
    'result',
    'button',
    'passwordMeter',
    'passwordHint'
  ]

  connect() {
    this.navigationTimer = null
  }

  disconnect() {
    if (this.navigationTimer) window.clearTimeout(this.navigationTimer)
  }

  switchPage(event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      return
    }

    const destination = event.currentTarget.href
    event.preventDefault()

    const direction = event.currentTarget.dataset.authDirection === 'backward'
      ? 'backward'
      : 'forward'

    this.element.classList.add(`is-switching-${direction}`)
    this.navigationTimer = window.setTimeout(() => {
      window.location.assign(destination)
    }, 280)
  }

  togglePassword(event) {
    const button = event.currentTarget
    const input = button.closest('.auth-input')?.querySelector('input')
    if (!input) return

    const showPassword = input.type === 'password'
    input.type = showPassword ? 'text' : 'password'

    const icon = button.querySelector('i')
    icon?.classList.toggle('fa-eye', !showPassword)
    icon?.classList.toggle('fa-eye-slash', showPassword)
    button.setAttribute('aria-pressed', String(showPassword))
    button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password')
  }

  updatePasswordStrength() {
    if (!this.hasPasswordMeterTarget || !this.hasPasswordHintTarget) return

    const password = this.passwordTarget.value
    let score = 0

    if (password.length >= 8) score += 1
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1
    if (/\d/.test(password)) score += 1
    if (/[^A-Za-z0-9]/.test(password)) score += 1

    const levels = [
      { width: '0%', color: '#bd7772', text: 'Use 8+ characters with letters and numbers.' },
      { width: '25%', color: '#c97868', text: 'Password strength: weak' },
      { width: '50%', color: '#ce9a4b', text: 'Password strength: fair' },
      { width: '75%', color: '#609b7f', text: 'Password strength: good' },
      { width: '100%', color: '#157a61', text: 'Password strength: strong' }
    ]
    const level = password.length === 0 ? levels[0] : levels[Math.max(1, score)]

    this.passwordMeterTarget.style.setProperty('--password-strength', level.width)
    this.passwordMeterTarget.style.setProperty('--strength-color', level.color)
    this.passwordHintTarget.textContent = level.text
  }

  async login(event) {
    event.preventDefault()
    if (!this.validateForm(event.currentTarget)) return

    this.setLoading(true, 'Signing in…')
    this.showResult('Checking your account securely…', 'loading')

    try {
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: this.emailTarget.value.trim(),
          password: this.passwordTarget.value
        }),
        credentials: 'include'
      })

      const data = await safeJson(response)

      if (!response.ok) {
        this.showResult(data?.error || 'Unable to sign in with these credentials.', 'error')
        this.setLoading(false)
        return
      }

      this.showResult('Login successful. Opening your workspace…', 'success')

      const roles = data?.user?.roles || []
      let redirectUrl = '/app/dashboard'

      if (roles.includes('ROLE_ADMIN')) {
        redirectUrl = '/admin/courses'
      } else if (roles.includes('ROLE_COMPANY')) {
        redirectUrl = '/company/dashboard'
      }

      window.setTimeout(() => window.location.assign(redirectUrl), 700)
    } catch (error) {
      console.error(error)
      this.showResult('Network error. Please check your connection and try again.', 'error')
      this.setLoading(false)
    }
  }

  async register(event) {
    event.preventDefault()
    if (!this.validateForm(event.currentTarget)) return

    const selectedAccountType = this.accountTypeTargets.find((radio) => radio.checked)?.value || 'employee'
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content

    this.setLoading(true, 'Creating workspace…')
    this.showResult('Setting up your learning account…', 'loading')

    try {
      const response = await fetch('/api/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        credentials: 'include',
        body: JSON.stringify({
          email: this.emailTarget.value.trim(),
          password: this.passwordTarget.value,
          userName: this.userNameTarget.value.trim(),
          role: selectedAccountType
        })
      })

      const data = await safeJson(response)

      if (!response.ok) {
        this.showResult(data?.error || data?.message || 'Unable to create your account.', 'error')
        this.setLoading(false)
        return
      }

      this.showResult('Account created. Opening your new workspace…', 'success')

      const roles = data?.user?.roles || []
      let redirectUrl = '/app/dashboard'

      if (roles.includes('ROLE_ADMIN')) {
        redirectUrl = '/admin/courses'
      } else if (roles.includes('ROLE_COMPANY')) {
        redirectUrl = '/company/dashboard'
      }

      window.setTimeout(() => window.location.assign(redirectUrl), 800)
    } catch (error) {
      console.error(error)
      this.showResult('Network error. Please check your connection and try again.', 'error')
      this.setLoading(false)
    }
  }

  async logout(event) {
    event.preventDefault()

    try {
      const response = await fetch('/api/logout', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })

      if (!response.ok) {
        const error = await safeJson(response)
        console.error('Logout error:', error?.error)
        return
      }

      window.location.href = '/login'
    } catch (error) {
      console.error('Network error:', error)
    }
  }

  validateForm(form) {
    if (form.checkValidity()) return true
    form.reportValidity()
    return false
  }

  setLoading(isLoading, label = '') {
    if (!this.hasButtonTarget) return

    const button = this.buttonTarget
    const text = button.querySelector('span')

    if (text && !button.dataset.defaultLabel) {
      button.dataset.defaultLabel = text.textContent.trim()
    }

    button.disabled = isLoading
    button.classList.toggle('is-loading', isLoading)

    if (text) {
      text.textContent = isLoading ? label : button.dataset.defaultLabel
    }
  }

  showResult(message, state) {
    if (!this.hasResultTarget) return

    this.resultTarget.textContent = message
    this.resultTarget.classList.remove('is-success', 'is-error', 'is-loading')
    this.resultTarget.classList.add(`is-${state}`)
  }
}

async function safeJson(response) {
  try {
    return await response.json()
  } catch {
    return null
  }
}
