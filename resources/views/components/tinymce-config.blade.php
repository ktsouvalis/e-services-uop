
<script src="{{ asset('tinymce/tinymce.min.js') }}" referrerpolicy="origin"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
      selector: 'textarea#myeditorinstance', // Replace this CSS selector to match the placeholder element for TinyMCE
      plugins: 'lists link',
      toolbar: 'bold italic underline | link | bullist numlist | removeformat',
      menubar: false,
      elementpath: false, // This line removes the element path
      statusbar: false,   // This line removes the status bar
    });
  });
</script>