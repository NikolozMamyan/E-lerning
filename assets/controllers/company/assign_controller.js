// controllers/assign_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = [
    "modal", "courseName",
    "teamTab", "emailTab",
    "tabs", "searchInput",
    "table", "resultsCount", "noResults",
    "pagination", "tableWrapper",
    "userEmail", "emailError", "csrfToken",
    "submitBtn", "btnText", "spinner"
  ]

  static values = {
    hasCollabs: Boolean,
    rowsPerPage: { type: Number, default: 10 },
    currentPage: { type: Number, default: 1 },
    currentCourseId: String
  }

  // ===== Lifecycle =====
  connect() {
    // Init table on connect
    if (this.hasTableTarget) {
      this._allRows = Array.from(this.tableTarget.getElementsByClassName("user-row"))
      this.filteredRows = this._allRows.slice()
      this.displayPage()
    }
  }

  // ===== Public actions (data-action) =====
  tryAssign(event) {
    const btn = event.currentTarget
    const courseId = btn.dataset.courseId
    const courseTitle = btn.dataset.courseTitle

    if (!this.hasCollabsValue) {
      this.showNotification("You don't have any collaboration yet", "error")
      return
    }

    this.openAssignModal(courseId, courseTitle)
  }

  open(event) {
    // optionnel si tu veux ouvrir depuis autre élément
    const el = event?.currentTarget
    this.openAssignModal(el?.dataset.courseId, el?.dataset.courseTitle)
  }

  close() {
    this.modalTarget.classList.remove("active")
    document.body.style.overflow = ""
    this.currentCourseIdValue = null
  }

  onOverlayClick(event) {
    if (event.target === this.modalTarget) this.close()
  }

  handleKeydown(event) {
    if (event.key === "Escape") this.close()
  }

  showTeamTab() { this.switchTab("team") }
  showEmailTab() { this.switchTab("email") }

  filterTable() {
    if (!this.hasSearchInputTarget || !this.hasTableTarget) return

    const filter = this.searchInputTarget.value.toLowerCase()
    const rows = this.tableTarget.getElementsByClassName("user-row")

    this.filteredRows = []
    let visibleCount = 0

    for (let row of rows) {
      const name = row.cells[0].textContent.toLowerCase()
      const email = row.cells[1] ? row.cells[1].textContent.toLowerCase() : ""
      if (name.includes(filter) || email.includes(filter)) {
        this.filteredRows.push(row)
        visibleCount++
      }
    }

    this.resultsCountTarget.textContent = visibleCount

    if (visibleCount === 0) {
      this.noResultsTarget.style.display = "block"
      this.tableTarget.style.display = "none"
      this.paginationTarget.style.display = "none"
    } else {
      this.noResultsTarget.style.display = "none"
      this.tableTarget.style.display = "table"
      this.currentPageValue = 1
      this.displayPage()
    }
  }

  goToPage(event) {
    const page = Number(event.currentTarget.dataset.page)
    this.currentPageValue = page
    this.displayPage()
    if (this.hasTableWrapperTarget) this.tableWrapperTarget.scrollTop = 0
  }

  quickAssign(event) {
    if (!this.currentCourseIdValue) {
      this.showNotification("Error: No course selected", "error")
      return
    }

    // désactive tous les boutons Assign le temps de la soumission
    this.element.querySelectorAll(".assign-btn-quick").forEach(btn => {
      btn.disabled = true
      btn.style.opacity = "0.6"
    })

    const email = event.currentTarget.dataset.email

    // form POST invisible
    const form = document.createElement("form")
    form.method = "POST"
    form.action = `/company/pay/course/${this.currentCourseIdValue}`

    const emailInput = document.createElement("input")
    emailInput.type = "hidden"
    emailInput.name = "email"
    emailInput.value = email
    form.appendChild(emailInput)

    const tokenInput = document.createElement("input")
    tokenInput.type = "hidden"
    tokenInput.name = "_token"
    tokenInput.value = this.csrfTokenTarget.value
    form.appendChild(tokenInput)

    document.body.appendChild(form)
    form.submit()
  }

  submitEmailForm(event) {
    event.preventDefault()

    const email = this.userEmailTarget.value.trim()
    // validation
    if (!email) {
      this.emailErrorTarget.innerHTML = this._errorIcon() + "Please enter an email address"
      return
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(email)) {
      this.emailErrorTarget.innerHTML = this._errorIcon() + "Please enter a valid email address"
      return
    }
    this.emailErrorTarget.textContent = ""

    // loading state
    this.submitBtnTarget.disabled = true
    this.btnTextTarget.style.display = "none"
    this.spinnerTarget.style.display = "inline-block"

    // submit
    const form = event.currentTarget
    form.action = `/company/pay/course/${this.currentCourseIdValue}`
    form.method = "POST"
    form.submit()
  }

  assignToUser(event) {
    // legacy helper si besoin depuis le tableau
    const email = event.currentTarget.dataset.email
    this.switchTab("email")
    this.userEmailTarget.value = email
    this.userEmailTarget.focus()
  }

  // ===== Private helpers =====
  openAssignModal(courseId, courseTitle) {
    this.currentCourseIdValue = courseId
    this.courseNameTarget.textContent = courseTitle

    // reset form & search
    if (this.hasUserEmailTarget) this.userEmailTarget.value = ""
    if (this.hasEmailErrorTarget) this.emailErrorTarget.textContent = ""
    if (this.hasSearchInputTarget) this.searchInputTarget.value = ""

    this.switchTab("team")
    this.filterTable()

    this.modalTarget.classList.add("active")
    document.body.style.overflow = "hidden"

    setTimeout(() => {
      if (this.hasSearchInputTarget) this.searchInputTarget.focus()
    }, 300)
  }

  switchTab(tab) {
    // tabs header
    this.tabsTargets.forEach(btn => btn.classList.remove("active"))
    this.teamTabTarget.classList.remove("active")
    this.emailTabTarget.classList.remove("active")

    if (tab === "team") {
      this.element.querySelector('[data-tab="team"]')?.classList.add("active")
      this.teamTabTarget.classList.add("active")
    } else {
      this.element.querySelector('[data-tab="email"]')?.classList.add("active")
      this.emailTabTarget.classList.add("active")
      setTimeout(() => this.userEmailTarget?.focus(), 100)
    }
  }

  displayPage() {
    const allRows = this._allRows || []
    // cache toutes les lignes
    allRows.forEach(r => (r.style.display = "none"))

    const start = (this.currentPageValue - 1) * this.rowsPerPageValue
    const end = start + this.rowsPerPageValue
    const rowsToShow = (this.filteredRows && this.filteredRows.length > 0) ? this.filteredRows : allRows

    for (let i = start; i < end && i < rowsToShow.length; i++) {
      rowsToShow[i].style.display = ""
    }
    this.generatePagination(rowsToShow.length)
  }

  generatePagination(totalRows) {
    if (!this.hasPaginationTarget) return

    const totalPages = Math.ceil(totalRows / this.rowsPerPageValue)
    if (totalPages <= 1) {
      this.paginationTarget.style.display = "none"
      this.paginationTarget.innerHTML = ""
      return
    }

    this.paginationTarget.style.display = "flex"
    this.paginationTarget.innerHTML = ""

    const addBtn = (label, page, disabled = false, isActive = false) => {
      const btn = document.createElement("button")
      btn.className = "page-btn" + (isActive ? " active" : "")
      btn.textContent = label
      btn.dataset.action = "assign#goToPage"
      btn.dataset.page = page
      btn.disabled = disabled
      this.paginationTarget.appendChild(btn)
    }

    // Prev
    addBtn("←", Math.max(1, this.currentPageValue - 1), this.currentPageValue === 1)

    // Pages with ellipsis
    let startPage = Math.max(1, this.currentPageValue - 2)
    let endPage = Math.min(totalPages, this.currentPageValue + 2)
    if (this.currentPageValue <= 3) endPage = Math.min(5, totalPages)
    if (this.currentPageValue >= totalPages - 2) startPage = Math.max(totalPages - 4, 1)

    if (startPage > 1) {
      addBtn("1", 1, false, this.currentPageValue === 1)
      if (startPage > 2) this.paginationTarget.appendChild(this._ellipsis())
    }

    for (let i = startPage; i <= endPage; i++) {
      addBtn(String(i), i, false, i === this.currentPageValue)
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) this.paginationTarget.appendChild(this._ellipsis())
      addBtn(String(totalPages), totalPages, false, this.currentPageValue === totalPages)
    }

    // Next
    addBtn("→", Math.min(totalPages, this.currentPageValue + 1), this.currentPageValue === totalPages)
  }

  // ===== UI bits =====
  showNotification(message, type = "info") {
    const n = document.createElement("div")
    n.className = `toast toast--${type}`
    n.textContent = message
    Object.assign(n.style, {
      position: "fixed",
      bottom: "20px",
      right: "20px",
      padding: "16px 20px",
      background: type === "error" ? "#ef4444" : "#3b82f6",
      color: "#fff",
      borderRadius: "8px",
      boxShadow: "0 4px 12px rgba(0,0,0,.15)",
      zIndex: 10000,
      fontSize: "14px",
      fontWeight: 500,
      animation: "slideIn .3s ease"
    })
    document.body.appendChild(n)
    setTimeout(() => {
      n.style.animation = "slideOut .3s ease"
      setTimeout(() => n.remove(), 300)
    }, 3000)
  }

  _ellipsis() {
    const span = document.createElement("span")
    span.textContent = "..."
    Object.assign(span.style, { padding: "8px 4px", color: "#9ca3af" })
    return span
  }

  _errorIcon() {
    return `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    `
  }
}
