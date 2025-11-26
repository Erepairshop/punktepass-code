jQuery(document).ready(function($){


  // Új review
  $(document).on('submit','.ppv-review-form',function(e){
    e.preventDefault();
    var form = $(this);
    var data = {
      action: 'ppv_submit_review',
      nonce: ppvReviews.nonce,
      store_id: form.find('[name=store_id]').val(),
      rating: form.find('[name=rating]:checked').val(),
      comment: form.find('[name=comment]').val()
    };
    $.post(ppvReviews.ajaxurl,data,function(resp){
      if(resp.success){ location.reload(); }
      else { alert(resp.data.msg); }
    });
  });

  // Reply
  $(document).on('submit','.ppv-reply-form',function(e){
    e.preventDefault();
    var form = $(this);
    var data = {
      action: 'ppv_reply_review',
      nonce: ppvReviews.nonce,
      review_id: form.find('[name=review_id]').val(),
      reply: form.find('[name=reply]').val()
    };
    $.post(ppvReviews.ajaxurl,data,function(resp){
      if(resp.success){ location.reload(); }
      else { alert(resp.data.msg); }
    });
  });

  // Helpful
  $(document).on('click','.ppv-helpful',function(e){
    e.preventDefault();
    var btn = $(this);
    var data = {
      action: 'ppv_helpful_review',
      nonce: ppvReviews.nonce,
      review_id: btn.data('id')
    };
    $.post(ppvReviews.ajaxurl,data,function(resp){
      if(resp.success){
        var match = btn.text().match(/\d+/);
        if(match){
          btn.text("Hilfreich ("+(parseInt(match[0])+1)+")");
        }
      } else { alert(resp.data.msg); }
    });
  });

});
