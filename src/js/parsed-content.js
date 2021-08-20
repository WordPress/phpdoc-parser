/**
 * Admin extras backend JS.
 */

(function ($) {
  var ticketNumber = $("#wporg_parsed_ticket"),
    attachButton = $("#wporg_ticket_attach"),
    detachButton = $("#wporg_ticket_detach"),
    ticketInfo = $("#wporg_ticket_info"),
    spinner = $(".attachment_controls .spinner");

  var handleTicket = function (event) {
    event.preventDefault();

    var $this = $(this),
      attachAction = "attach" == event.data.action;

    spinner.addClass("is-active");

    // Searching ... text.
    if (attachAction) {
      ticketInfo.text(wporgParsedContent.searchText);
    }

    var request = wp.ajax.post(
      attachAction ? "wporg_attach_ticket" : "wporg_detach_ticket",
      {
        ticket: ticketNumber.val(),
        nonce: $this.data("nonce"),
        post_id: $this.data("id"),
      }
    );

    // Success.
    request.done(function (response) {
      // Refresh the nonce.
      $this.data("nonce", response.new_nonce);

      // Hide or show the parsed content boxes.
      $(".wporg_parsed_content").each(function () {
        attachAction ? $(this).show() : $(this).hide();
      });

      $(".wporg_parsed_readonly").each(function () {
        attachAction ? $(this).hide() : $(this).show();
      });

      var otherButton = attachAction ? detachButton : attachButton;

      // Toggle the buttons.
      $this.hide();
      otherButton.css("display", "inline-block");

      // Update the ticket info text.
      ticketInfo.html(response.message).show();

      // Clear the ticket number when detaching.
      if (!attachAction) {
        ticketNumber.val("");
      }

      spinner.removeClass("is-active");

      // Set or unset the ticket link icon.
      $(".ticket_info_icon").toggleClass(
        "dashicons dashicons-external",
        attachAction
      );

      // Set the ticket number to readonly when a ticket is attached.
      attachAction
        ? ticketNumber.prop("readonly", "readonly")
        : ticketNumber.removeAttr("readonly");
    });

    // Error.
    request.fail(function (response) {
      // Refresh the nonce.
      $this.data("nonce", response.new_nonce);

      // Retry text.
      ticketInfo.text(wporgParsedContent.retryText);

      spinner.removeClass("is-active");
    });
  };

  attachButton.on("click", { action: "attach" }, handleTicket);
  detachButton.on("click", { action: "detach" }, handleTicket);
})(jQuery);
