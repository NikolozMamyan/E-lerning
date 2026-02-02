import { Controller } from "@hotwired/stimulus"

// Connects to data-controller="progress"
export default class extends Controller {
  static values = {
    videoId: Number,
    expectedDuration: Number,
    updateUrl: String,
    popupImage: String // chemin vers ton image de congratulations
  }

  connect() {
    this.video = this.element
    if (!this.video) return

    this.realDuration = 0
    this.completedSent = false
    this.lastSent = 0

    this.UPDATE_INTERVAL_MS = 10000
    this.COMPLETE_THRESHOLD = 0.97

    this.initEvents()
  }

  initEvents() {
    this.video.addEventListener("loadedmetadata", () => {
      if (Number.isFinite(this.video.duration) && this.video.duration > 0) {
        this.realDuration = this.video.duration
      }
    })

    setInterval(() => {
      if (!this.completedSent) this.sendProgress(this.video.currentTime)
    }, this.UPDATE_INTERVAL_MS)

    this.video.addEventListener("pause", () => {
      if (!this.completedSent) this.sendProgress(this.video.currentTime)
    })

    this.video.addEventListener("timeupdate", () => {
      if (!this.completedSent && this.shouldComplete()) {
        this.completedSent = true
        this.sendProgress(this.effectiveDuration(), false, true) // 👈 envoie completed: true
        this.showPopup()
      }
    })

    this.video.addEventListener("ended", () => {
      if (!this.completedSent) {
        this.completedSent = true
        this.sendProgress(this.effectiveDuration(), false, true) // 👈 envoie completed: true
        this.showPopup()
      }
    })

    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden" && !this.completedSent) {
        this.sendProgress(this.video.currentTime, true)
      }
    })
    window.addEventListener("beforeunload", () => {
      if (!this.completedSent) this.sendProgress(this.video.currentTime, true)
    })
  }

  effectiveDuration() {
    if (Number.isFinite(this.realDuration) && this.realDuration > 0) {
      return Math.floor(this.realDuration)
    }
    if (this.video.seekable && this.video.seekable.length > 0) {
      try {
        return Math.floor(this.video.seekable.end(this.video.seekable.length - 1))
      } catch (e) {}
    }
    return this.expectedDurationValue || 0
  }

  shouldComplete() {
    const dur = this.effectiveDuration()
    if (!dur) return false
    return this.video.ended || (this.video.currentTime >= dur * this.COMPLETE_THRESHOLD)
  }

  sendProgress(seconds, useBeacon = false, forceComplete = false) {
    const sec = Math.max(0, Math.floor(seconds || this.video.currentTime || 0))
    if (!useBeacon && sec === this.lastSent && !forceComplete) return
    this.lastSent = sec

    const payload = JSON.stringify({
      watched: sec,
      completed: forceComplete // 👈 envoie le flag si besoin
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
    console.log("🎉 showPopup called!") // Debug

    const overlay = document.createElement("div")
    overlay.classList.add("progress-popup-overlay") // plus propre pour CSS

    const popup = document.createElement("div")
    popup.classList.add("progress-popup")

    const img = document.createElement("img")
    img.src = this.popupImageValue
    img.style.width = "120px"
    img.style.marginBottom = "1rem"

    const title = document.createElement("h2")
    title.textContent = "🎉 Congratulations!"
    title.style.marginBottom = "0.5rem"

    const text = document.createElement("p")
    text.textContent = "You successfully completed this lesson."

    const btn = document.createElement("button")
    btn.textContent = "OK"
    btn.classList.add("popup-btn")
    btn.addEventListener("click", () => window.location.reload())

    popup.appendChild(img)
    popup.appendChild(title)
    popup.appendChild(text)
    popup.appendChild(btn)

    overlay.appendChild(popup)
    document.body.appendChild(overlay)
  }
}