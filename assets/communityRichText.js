const initCommunityRichTextEditors = () => {
  const QuillEditor = window.Quill;
  if (typeof QuillEditor !== 'function') return;

  document.querySelectorAll('textarea[data-community-rich-text]').forEach((textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.richTextReady === 'true') return;

    textarea.dataset.richTextReady = 'true';
    textarea.hidden = true;

    const editor = document.createElement('div');
    editor.className = 'admin-community__rich-editor';
    textarea.insertAdjacentElement('afterend', editor);

    const quill = new QuillEditor(editor, {
      theme: 'snow',
      placeholder: textarea.placeholder || 'Write a description…',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'link'],
          ['clean'],
        ],
      },
    });

    const initialValue = textarea.value.trim();
    if (initialValue !== '') {
      if (/<\/?[a-z][\s\S]*>/i.test(initialValue)) {
        quill.clipboard.dangerouslyPasteHTML(initialValue);
      } else {
        quill.setText(initialValue);
      }
    }

    const editable = editor.querySelector('.ql-editor');
    const label = textarea.closest('div')?.querySelector(`label[for="${CSS.escape(textarea.id)}"]`);
    if (editable instanceof HTMLElement && label?.textContent) {
      editable.setAttribute('aria-label', label.textContent.trim());
    }

    const syncValue = () => {
      textarea.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
    };

    quill.on('text-change', syncValue);
    textarea.form?.addEventListener('submit', syncValue);
    syncValue();
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCommunityRichTextEditors);
} else {
  initCommunityRichTextEditors();
}
