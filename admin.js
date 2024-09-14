jQuery(document).ready(function ($) {
  $("#start-sanitization").on("click", function () {
    var $button = $(this);
    var $progress = $("#sanitization-progress");
    var $bar = $("#sanitization-bar");
    var $status = $("#sanitization-status");

    $button.prop("disabled", true);
    $progress.show();

    function runBatch(offset) {
      $.ajax({
        url: wpDataSanitizer.ajax_url,
        type: "POST",
        data: {
          action: "sanitize_batch",
          nonce: wpDataSanitizer.nonce,
          offset: offset,
        },
        success: function (response) {
          $bar.val(response.progress);
          $status.text(
            "Processed " +
              (offset + response.processed) +
              " out of " +
              response.total
          );

          if (!response.done) {
            runBatch(offset + response.processed);
          } else {
            $status.text("Sanitization completed!");
            $button.prop("disabled", false);
          }
        },
        error: function () {
          $status.text("An error occurred. Please try again.");
          $button.prop("disabled", false);
        },
      });
    }

    runBatch(0);
  });
});
