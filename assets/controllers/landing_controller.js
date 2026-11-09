import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['menu', 'image'];

  imageTargetConnected(image) {
    if (image.complete && image.naturalWidth === 0) {
      image.hidden = true;
    }
  }

  hideImage(event) {
    event.currentTarget.hidden = true;
  }

  navigate(event) {
    if (event.target.closest('a')) {
      this.closeMenu();
    }
  }

  closeOutside(event) {
    if (!this.menuTarget.contains(event.target)) {
      this.closeMenu();
    }
  }

  closeMenu(event) {
    if (event?.type === 'keydown' && this.menuTarget.open) {
      this.menuTarget.querySelector('summary').focus();
    }
    this.menuTarget.open = false;
  }
}
