import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['content']

    connect() {
        this.loadUser()
    }

    async loadUser() {
        try {
            const res = await fetch('/api/me', { credentials: 'include' })

            if (!res.ok) throw new Error()

            const user = await res.json()

            this.contentTarget.innerHTML = `
     
            `
        } catch {
            // non connecté = on affiche rien
            this.element.remove()
        }
    }

    async logout() {
        const res = await fetch('/api/logout', {
            method: 'POST',
            credentials: 'include'
        })

        if (res.ok) {
            window.location.href = '/login'
        }
    }
}