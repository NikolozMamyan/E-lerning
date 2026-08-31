import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = [
    "modal",
    "courseName",
    "searchInput",
    "table",
    "resultsCount",
    "noResults",
    "pagination",
    "tableWrapper",
    "memberCheckbox",
    "selectAll",
    "checkoutForm",
    "checkoutButton",
    "checkoutButtonText",
    "selectionCount",
    "selectionTotal",
    "unitPrice",
    "seatPlural",
  ]

  static values = {
    hasCollabs: Boolean,
    rowsPerPage: { type: Number, default: 10 },
    currentPage: { type: Number, default: 1 },
    currentCourseId: String,
    checkoutUrlTemplate: String,
  }

  connect() {
    this.unitPriceCents = 0
    this._allRows = this.hasTableTarget
      ? Array.from(this.tableTarget.querySelectorAll(".user-row"))
      : []
    this.filteredRows = this._allRows.slice()

    if (this.hasTableTarget) this.displayPage()
  }

  tryAssign(event) {
    if (!this.hasCollabsValue) {
      this.showNotification("Add at least one team member before assigning a course.", "error")
      return
    }

    const button = event.currentTarget
    this.openAssignModal(
      button.dataset.courseId,
      button.dataset.courseTitle,
      Number(button.dataset.coursePriceCents || 0),
    )
  }

  close() {
    if (!this.hasModalTarget) return

    this.modalTarget.classList.remove("active")
    this.modalTarget.setAttribute("aria-hidden", "true")
    document.body.style.overflow = ""
    this.currentCourseIdValue = ""
  }

  onOverlayClick(event) {
    if (event.target === this.modalTarget) this.close()
  }

  handleKeydown(event) {
    if (event.key === "Escape" && this.modalTarget.classList.contains("active")) this.close()
  }

  stopPropagation(event) {
    event.stopPropagation()
  }

  toggleRow(event) {
    if (event.target.closest("a, button, input, label")) return

    const checkbox = event.currentTarget.querySelector('[data-assign-target="memberCheckbox"]')
    if (!checkbox || checkbox.disabled) return

    checkbox.checked = !checkbox.checked
    this.updateSelection()
  }

  toggleAll() {
    const eligibleRows = this.filteredRows.filter((row) => {
      const checkbox = row.querySelector('[data-assign-target="memberCheckbox"]')
      return checkbox && !checkbox.disabled
    })

    eligibleRows.forEach((row) => {
      row.querySelector('[data-assign-target="memberCheckbox"]').checked = this.selectAllTarget.checked
    })
    this.updateSelection()
  }

  updateSelection() {
    const selected = this.memberCheckboxTargets.filter((checkbox) => checkbox.checked && !checkbox.disabled)
    const quantity = selected.length

    this.selectionCountTarget.textContent = String(quantity)
    this.seatPluralTarget.hidden = quantity === 1
    this.selectionTotalTarget.textContent = this.formatPrice(quantity * this.unitPriceCents)
    this.checkoutButtonTarget.disabled = quantity === 0
    this.checkoutButtonTextTarget.textContent = quantity === 0
      ? "Select employees"
      : `Buy ${quantity} seat${quantity === 1 ? "" : "s"}`

    this.updateSelectAllState()
    this._allRows.forEach((row) => {
      const checkbox = row.querySelector('[data-assign-target="memberCheckbox"]')
      row.classList.toggle("is-selected", Boolean(checkbox?.checked))
    })
  }

  filterTable() {
    if (!this.hasSearchInputTarget || !this.hasTableTarget) return

    const filter = this.searchInputTarget.value.trim().toLowerCase()
    this.filteredRows = this._allRows.filter((row) => (row.dataset.search || "").includes(filter))
    this.resultsCountTarget.textContent = String(this.filteredRows.length)
    this.currentPageValue = 1

    const hasResults = this.filteredRows.length > 0
    this.noResultsTarget.hidden = hasResults
    this.tableTarget.hidden = !hasResults
    this.displayPage()
    this.updateSelectAllState()
  }

  goToPage(event) {
    this.currentPageValue = Number(event.currentTarget.dataset.page)
    this.displayPage()
    if (this.hasTableWrapperTarget) this.tableWrapperTarget.scrollTop = 0
  }

  submitSelection(event) {
    const quantity = this.memberCheckboxTargets.filter((checkbox) => checkbox.checked && !checkbox.disabled).length
    if (quantity === 0) {
      event.preventDefault()
      this.showNotification("Select at least one available team member.", "error")
      return
    }

    this.checkoutButtonTarget.disabled = true
    this.checkoutButtonTextTarget.textContent = "Opening secure checkout..."
  }

  openAssignModal(courseId, courseTitle, unitPriceCents) {
    this.currentCourseIdValue = String(courseId)
    this.unitPriceCents = unitPriceCents
    this.courseNameTarget.textContent = courseTitle
    this.unitPriceTarget.textContent = this.formatPrice(unitPriceCents)
    this.checkoutFormTarget.action = this.checkoutUrlTemplateValue.replace("__COURSE__", String(courseId))

    if (this.hasSearchInputTarget) this.searchInputTarget.value = ""
    this.filteredRows = this._allRows.slice()
    this.resultsCountTarget.textContent = String(this.filteredRows.length)
    this.currentPageValue = 1
    this.applyCourseEligibility(courseId)
    this.displayPage()
    this.updateSelection()

    this.modalTarget.classList.add("active")
    this.modalTarget.setAttribute("aria-hidden", "false")
    document.body.style.overflow = "hidden"

    window.setTimeout(() => this.searchInputTarget?.focus(), 250)
  }

  applyCourseEligibility(courseId) {
    this._allRows.forEach((row) => {
      let enrolledCourseIds = []
      try {
        enrolledCourseIds = JSON.parse(row.dataset.enrolledCourseIds || "[]").map(Number)
      } catch {
        enrolledCourseIds = []
      }

      const alreadyAssigned = enrolledCourseIds.includes(Number(courseId))
      const checkbox = row.querySelector('[data-assign-target="memberCheckbox"]')
      const accessState = row.querySelector("[data-assign-access-state]")

      checkbox.checked = false
      checkbox.disabled = alreadyAssigned
      row.classList.toggle("is-unavailable", alreadyAssigned)
      if (accessState) {
        accessState.className = `company-access-state${alreadyAssigned ? " is-assigned" : ""}`
        accessState.innerHTML = alreadyAssigned
          ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>Assigned'
          : '<i class="fa-solid fa-plus" aria-hidden="true"></i>Available'
      }
    })
  }

  displayPage() {
    if (!this.hasTableTarget) return

    this._allRows.forEach((row) => { row.hidden = true })

    const start = (this.currentPageValue - 1) * this.rowsPerPageValue
    const visibleRows = this.filteredRows.slice(start, start + this.rowsPerPageValue)
    visibleRows.forEach((row) => { row.hidden = false })
    this.generatePagination(this.filteredRows.length)
  }

  generatePagination(totalRows) {
    if (!this.hasPaginationTarget) return

    const totalPages = Math.ceil(totalRows / this.rowsPerPageValue)
    this.paginationTarget.innerHTML = ""
    this.paginationTarget.hidden = totalPages <= 1
    if (totalPages <= 1) return

    const addButton = (label, page, disabled = false, active = false) => {
      const button = document.createElement("button")
      button.type = "button"
      button.className = `page-btn${active ? " active" : ""}`
      button.textContent = label
      button.dataset.action = "assign#goToPage"
      button.dataset.page = String(page)
      button.disabled = disabled
      this.paginationTarget.appendChild(button)
    }

    addButton("‹", Math.max(1, this.currentPageValue - 1), this.currentPageValue === 1)

    const firstPage = Math.max(1, this.currentPageValue - 2)
    const lastPage = Math.min(totalPages, this.currentPageValue + 2)
    for (let page = firstPage; page <= lastPage; page += 1) {
      addButton(String(page), page, false, page === this.currentPageValue)
    }

    addButton("›", Math.min(totalPages, this.currentPageValue + 1), this.currentPageValue === totalPages)
  }

  updateSelectAllState() {
    if (!this.hasSelectAllTarget) return

    const eligible = this.filteredRows
      .map((row) => row.querySelector('[data-assign-target="memberCheckbox"]'))
      .filter((checkbox) => checkbox && !checkbox.disabled)
    const selectedCount = eligible.filter((checkbox) => checkbox.checked).length

    this.selectAllTarget.checked = eligible.length > 0 && selectedCount === eligible.length
    this.selectAllTarget.indeterminate = selectedCount > 0 && selectedCount < eligible.length
    this.selectAllTarget.disabled = eligible.length === 0
  }

  formatPrice(cents) {
    return new Intl.NumberFormat("en-LU", {
      style: "currency",
      currency: "EUR",
      minimumFractionDigits: 2,
    }).format(cents / 100)
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `toast toast--${type}`
    notification.textContent = message
    document.body.appendChild(notification)
    window.setTimeout(() => notification.remove(), 3200)
  }
}
