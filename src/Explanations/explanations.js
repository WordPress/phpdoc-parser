/**
 * Explanations JS.
 */

(function ($) {
  //
  // Explanations AJAX handlers.
  //

  var statusLabel = $("#status-label"),
    createLink = $("#create-expl"),
    unPublishLink = $("#unpublish-expl"),
    rowActions = $("#expl-row-actions");

  var rowCreateLink = $(".create-expl");

  /**
   * AJAX handler for creating and associating a new explanation post.
   *
   * @param {object} event Event object.
   */
  function createExplanation(event) {
    event.preventDefault();

    wp.ajax.send("new_explanation", {
      success: createExplSuccess,
      error: createExplError,
      data: {
        nonce: $(this).data("nonce"),
        post_id: $(this).data("id"),
        context: event.data.context,
      },
    });
  }

  /**
   * Success callback for creating a new explanation via AJAX.
   *
   * @param {object} data Data response object.
   */
  function createExplSuccess(data) {
    var editLink =
      '<a href="post.php?post=' +
      data.post_id +
      '&action=edit">' +
      wporg.editContentLabel +
      "</a>";

    if ("edit" == data.context) {
      // Action in the parsed post type edit screen.
      createLink.hide();
      rowActions.html(editLink);
      statusLabel.text(wporg.statusLabel.draft);
    } else {
      // Row link in the list table.
      $("#post-" + data.parent_id + " .add-expl").html(editLink + " | ");
    }
  }

  /**
   * Error callback for creating a new explanation via AJAX.
   *
   * @param {object} data Data response object.
   */
  function createExplError(data) {}

  /**
   * Handler for un-publishing an existing Explanation.
   *
   * @param {object} event Event object.
   */
  function unPublishExplantaion(event) {
    event.preventDefault();

    wp.ajax.send("un_publish", {
      success: unPublishSuccess,
      error: unPublishError,
      data: {
        nonce: $(this).data("nonce"),
        post_id: $(this).data("id"),
      },
    });
  }

  /**
   * Success callback for un-publishing an explanation via AJAX.
   *
   * @param {object} data Data response object.
   */
  function unPublishSuccess(data) {
    if (statusLabel.hasClass("pending") || statusLabel.hasClass("publish")) {
      statusLabel.removeClass("pending publish").text(wporg.statusLabel.draft);
    }
    unPublishLink.hide();
  }

  /**
   * Error callback for un-publishing an explanation via AJAX.
   *
   * @param {object} data Data response object.
   */
  function unPublishError(data) {}

  // Events.
  createLink.on("click", { context: "edit" }, createExplanation);
  rowCreateLink.on("click", { context: "list" }, createExplanation);
  unPublishLink.on("click", unPublishExplantaion);
})(jQuery);
