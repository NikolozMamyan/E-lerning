import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['search', 'course', 'count', 'empty'];

  filter() {
    const query = this.normalize(this.searchTarget.value.trim());
    let visible = 0;

    this.courseTargets.forEach((course) => {
      const matches = this.normalize(course.dataset.search).includes(query);
      course.hidden = !matches;
      if (matches) visible++;
    });

    this.countTarget.textContent = `${visible} of ${this.courseTargets.length} courses`;
    this.emptyTarget.hidden = visible !== 0 || query === '';
  }

  clear() {
    this.searchTarget.value = '';
    this.filter();
    this.searchTarget.focus();
  }

  normalize(value) {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }
}
