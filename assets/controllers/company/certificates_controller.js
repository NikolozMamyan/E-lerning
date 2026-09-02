import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["selectAll", "checkbox", "downloadButton", "selectionCount"]

  connect() {
    this.updateSelection()
  }

  toggleAll() {
    this.checkboxTargets.forEach((checkbox) => {
      if (!checkbox.disabled) checkbox.checked = this.selectAllTarget.checked
    })
    this.updateSelection()
  }

  updateSelection() {
    const eligible = this.checkboxTargets.filter((checkbox) => !checkbox.disabled)
    const selected = eligible.filter((checkbox) => checkbox.checked)

    this.selectionCountTarget.textContent = String(selected.length)
    this.downloadButtonTarget.disabled = selected.length === 0
    this.selectAllTarget.checked = eligible.length > 0 && selected.length === eligible.length
    this.selectAllTarget.indeterminate = selected.length > 0 && selected.length < eligible.length
    this.selectAllTarget.disabled = eligible.length === 0

    this.checkboxTargets.forEach((checkbox) => {
      checkbox.closest("tr")?.classList.toggle("is-selected", checkbox.checked)
    })
  }

  submit(event) {
    if (!this.checkboxTargets.some((checkbox) => checkbox.checked && !checkbox.disabled)) {
      event.preventDefault()
    }
  }
}
