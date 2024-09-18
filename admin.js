jQuery(document).ready(function ($) {
  $("#start-sanitization").on("click", function () {
    var $button = $(this);
    var $progress = $("#sanitization-progress");
    var $bar = $("#sanitization-bar");
    var $status = $("#sanitization-status");

    $button.prop("disabled", true).text("Sanitizing...");
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
          if (response.success === false) {
            handleError(response.data);
            return;
          }

          var percentage = Math.round(response.progress);
          $bar.css("width", percentage + "%");
          $status.html(
            "Processed <strong>" +
              response.next_offset +
              "</strong> out of <strong>" +
              response.total +
              "</strong> items (" +
              percentage +
              "%)"
          );

          if (response.error_log.length > 0) {
            $status.append(
              "<br><strong>Warnings:</strong><br>" +
                response.error_log.join("<br>")
            );
          }

          if (!response.done) {
            runBatch(response.next_offset);
          } else {
            $status.html(
              "<strong>Sanitization completed successfully!</strong>"
            );
            if (response.error_log.length > 0) {
              $status.append(
                "<br><strong>Warnings:</strong><br>" +
                  response.error_log.join("<br>")
              );
            }
            $button.prop("disabled", false).text("Start Sanitization");
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          handleError({
            message: "AJAX error: " + textStatus + " - " + errorThrown,
            last_offset: offset,
          });
        },
      });
    }

    function handleError(errorData) {
      var errorMessage =
        "An error occurred. Last processed offset: " + errorData.last_offset;
      if (errorData.message) {
        errorMessage += "<br>Error message: " + errorData.message;
      }
      if (errorData.error_log && errorData.error_log.length > 0) {
        errorMessage +=
          "<br><strong>Error log:</strong><br>" +
          errorData.error_log.join("<br>");
      }
      $status.html("<strong class='error'>" + errorMessage + "</strong>");
      $button.prop("disabled", false).text("Start Sanitization");
    }

    runBatch(0);
  });
});
