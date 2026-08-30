import { Controller } from "@hotwired/stimulus"


// Connects to data-controller="progress"
export default class extends Controller {
  static values = {
    videoId: Number,
    expectedDuration: Number,
    updateUrl: String,
    popupImage: String,
    lastLesson: Boolean
  }
  connect() {
    // l'iframe est l'élément du controller
 this.player = new Vimeo.Player(this.element)

    this.realDuration = 0
    this.completedSent = false
    this.lastSent = 0

    this.UPDATE_INTERVAL_MS = 10000
    this.COMPLETE_THRESHOLD = 0.97

    this.initEvents()
  }

  initEvents() {
    // récupérer la durée de la vidéo
    this.player.getDuration().then(duration => {
      this.realDuration = duration
    })

    // envoyer régulièrement la progression
    setInterval(async () => {
      if (!this.completedSent) {
        const seconds = await this.player.getCurrentTime()
        this.sendProgress(seconds)
      }
    }, this.UPDATE_INTERVAL_MS)

    // écouter l'avancement
    this.player.on("timeupdate", (data) => {
      if (!this.completedSent && this.shouldComplete(data)) {
        this.completedSent = true
        this.sendProgress(this.realDuration, false, true)
        this.showPopup()
      }
    })

    // fin de la vidéo
    this.player.on("ended", () => {
      if (!this.completedSent) {
        this.completedSent = true
        this.sendProgress(this.realDuration, false, true)
        this.showPopup()
      }
    })

    // quand on change d’onglet ou ferme la page
    document.addEventListener("visibilitychange", async () => {
      if (document.visibilityState === "hidden" && !this.completedSent) {
        const seconds = await this.player.getCurrentTime()
        this.sendProgress(seconds, true)
      }
    })
    window.addEventListener("beforeunload", async () => {
      if (!this.completedSent) {
        const seconds = await this.player.getCurrentTime()
        this.sendProgress(seconds, true)
      }
    })
  }

  shouldComplete(data) {
    const dur = this.realDuration
    if (!dur) return false
    return data.percent >= this.COMPLETE_THRESHOLD
  }

  sendProgress(seconds, useBeacon = false, forceComplete = false) {
    const sec = Math.max(0, Math.floor(seconds || 0))
    if (!useBeacon && sec === this.lastSent && !forceComplete) return
    this.lastSent = sec

    const payload = JSON.stringify({
      watched: sec,
      completed: forceComplete
    })

    if (useBeacon && navigator.sendBeacon) {
      const blob = new Blob([payload], { type: "application/json" })
      navigator.sendBeacon(this.updateUrlValue, blob)
      return
    }

    fetch(this.updateUrlValue, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: payload
    })
      .then(r => r.json())
      .then(data => {
        if (data.completed) this.completedSent = true
        console.debug("Progress saved:", data)
      })
      .catch(err => console.error("Progress error:", err))
  }

  showPopup() {
    const overlay = document.createElement("div")
    overlay.classList.add("progress-popup-overlay")

    const popup = document.createElement("div")
    popup.classList.add("progress-popup")
    popup.setAttribute("role", "dialog")
    popup.setAttribute("aria-modal", "true")
    popup.setAttribute("aria-labelledby", "progress-popup-title")
    popup.setAttribute("aria-describedby", "progress-popup-description")

    const badge = document.createElement("div")
    badge.classList.add("progress-popup__badge")
    const badgeIcon = document.createElement("i")
    badgeIcon.className = "fa-solid fa-wand-magic-sparkles"
    badgeIcon.setAttribute("aria-hidden", "true")
    const badgeText = document.createElement("span")
    badgeText.textContent = this.lastLessonValue ? "Course milestone" : "Lesson completed"
    badge.append(badgeIcon, badgeText)

    const visual = document.createElement("div")
    visual.classList.add("progress-popup__visual")
    const img = document.createElement("img")
    img.src = this.popupImageValue
    img.alt = ""
    img.width = 82
    img.height = 82
    visual.appendChild(img)

    const title = document.createElement("h2")
    title.id = "progress-popup-title"
    title.textContent = this.lastLessonValue ? "Training complete!" : "Excellent progress!"

    const text = document.createElement("p")
    text.id = "progress-popup-description"
    text.textContent = this.lastLessonValue
      ? "You have completed the final lesson. Your next learning step is ready."
      : "This lesson is complete and your progress has been saved. Keep the momentum going."

    const status = document.createElement("div")
    status.classList.add("progress-popup__status")
    status.append(
      this.createPopupStatus("fa-circle-check", "Lesson progress", "100% watched"),
      this.createPopupStatus("fa-cloud-arrow-up", "Learning record", "Saved")
    )

    const btn = document.createElement("button")
    btn.type = "button"
    btn.classList.add("popup-btn")
    const btnLabel = document.createElement("span")
    btnLabel.textContent = this.lastLessonValue ? "View next step" : "Continue learning"
    const btnIcon = document.createElement("i")
    btnIcon.className = "fa-solid fa-arrow-right"
    btnIcon.setAttribute("aria-hidden", "true")
    btn.append(btnLabel, btnIcon)
    btn.addEventListener("click", () => window.location.reload())

    const note = document.createElement("div")
    note.classList.add("progress-popup__note")
    const noteIcon = document.createElement("i")
    noteIcon.className = "fa-solid fa-shield-halved"
    noteIcon.setAttribute("aria-hidden", "true")
    const noteText = document.createElement("span")
    noteText.textContent = "Your progress is securely saved"
    note.append(noteIcon, noteText)

    popup.append(badge, visual, title, text, status, btn, note)
    overlay.appendChild(popup)

    const course = this.element.closest(".course-detail")
    if (course) course.appendChild(overlay)
    else document.body.appendChild(overlay)

    window.setTimeout(() => btn.focus({ preventScroll: true }), 320)
  }

  createPopupStatus(icon, label, value) {
    const item = document.createElement("div")
    item.classList.add("progress-popup__status-item")

    const iconWrap = document.createElement("span")
    iconWrap.classList.add("progress-popup__status-icon")
    const statusIcon = document.createElement("i")
    statusIcon.className = `fa-solid ${icon}`
    statusIcon.setAttribute("aria-hidden", "true")
    iconWrap.appendChild(statusIcon)

    const copy = document.createElement("span")
    const small = document.createElement("small")
    small.textContent = label
    const strong = document.createElement("strong")
    strong.textContent = value
    copy.append(small, strong)

    item.append(iconWrap, copy)
    return item
  }

}
