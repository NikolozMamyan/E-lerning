import { Controller } from "@hotwired/stimulus"


// Connects to data-controller="progress"
export default class extends Controller {
  static values = {
    videoId: Number,
    expectedDuration: Number,
    updateUrl: String,
    popupImage: String
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

  const img = document.createElement("img")
  img.src = this.popupImageValue
  img.alt = "Congratulations"
  img.width = 120

  const title = document.createElement("h2")
  title.textContent = "🎉 Congratulations!"

  const text = document.createElement("p")
  text.textContent = "You successfully completed this lesson."

  const btn = document.createElement("button")
  btn.textContent = "OK"
  btn.classList.add("popup-btn")
  btn.addEventListener("click", () => overlay.remove())

  popup.append(img, title, text, btn)
  overlay.appendChild(popup)

  // ✅ IMPORTANT : append dans le wrapper video-container (pas dans l'iframe)
  const container = this.element.closest(".video-container")
  if (container) container.appendChild(overlay)
  else document.body.appendChild(overlay) // fallback
}

}
