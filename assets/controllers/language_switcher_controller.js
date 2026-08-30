import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    delay: { type: Number, default: 4500 },
    collapseDelay: { type: Number, default: 700 }
  }

  connect() {
    this.collapseTimer = null
    this.scheduleCollapseAfter(this.delayValue)
  }

  disconnect() {
    this.clearCollapseTimer()
  }

  expand() {
    this.clearCollapseTimer()
    this.element.classList.remove('is-minimized')
    this.element.setAttribute('aria-expanded', 'true')
  }

  scheduleCollapse(event) {
    if (event?.type === 'focusout' && this.element.contains(event.relatedTarget)) return

    this.scheduleCollapseAfter(this.collapseDelayValue)
  }

  scheduleCollapseAfter(delay) {
    this.clearCollapseTimer()
    this.collapseTimer = window.setTimeout(() => this.collapse(), delay)
  }

  collapse() {
    if (this.element.matches(':hover') || this.element.contains(document.activeElement)) return

    this.element.classList.add('is-minimized')
    this.element.setAttribute('aria-expanded', 'false')
  }

  clearCollapseTimer() {
    if (!this.collapseTimer) return

    window.clearTimeout(this.collapseTimer)
    this.collapseTimer = null
  }
}
