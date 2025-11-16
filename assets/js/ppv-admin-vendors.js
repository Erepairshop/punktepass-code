jQuery(function($){
  $(document).on('click', '.ppv-activate-pos', function(){
    const store = $(this).data('store');
    if (!confirm('POS aktivieren?')) return;
    $.post(ppvAdminVendors.ajax_url, {
      action: 'ppv_admin_toggle_pos',
      nonce: ppvAdminVendors.nonce,
      store_id: store,
      action_type: 'activate'
    }, function(resp){
      if (resp.success) {
        alert('POS aktiviert. PIN: ' + resp.data.pin);
        location.reload();
      } else {
        alert('Error: ' + (resp.data?.message || resp.data));
      }
    });
  });

  $(document).on('click', '.ppv-deactivate-pos', function(){
    const store = $(this).data('store');
    if (!confirm('POS deaktivieren?')) return;
    $.post(ppvAdminVendors.ajax_url, {
      action: 'ppv_admin_toggle_pos',
      nonce: ppvAdminVendors.nonce,
      store_id: store,
      action_type: 'deactivate'
    }, function(resp){
      if (resp.success) {
        alert('POS deaktiviert');
        location.reload();
      } else {
        alert('Error: ' + (resp.data?.message || resp.data));
      }
    });
  });

  $(document).on('click', '.ppv-new-pin', function(){
    const store = $(this).data('store');
    if (!confirm('Neues PIN generieren? (a régi inaktiválva lesz)')) return;
    $.post(ppvAdminVendors.ajax_url, {
      action: 'ppv_admin_new_pin',
      nonce: ppvAdminVendors.nonce,
      store_id: store
    }, function(resp){
      if (resp.success) {
        alert('Új PIN: ' + resp.data.pin);
        location.reload();
      } else {
        alert('Error: ' + (resp.data?.message || resp.data));
      }
    });
  });
  jQuery(document).on("click", ".ppv-admin-force-activate", function(){
  const storeID = jQuery(this).data("store");
  if (!confirm("Möchten Sie diesen Händler ohne Zahlung aktivieren?")) return;

  jQuery.post(ppvAdminVendors.ajax_url, {
    action: "ppv_admin_force_activate",
    nonce: ppvAdminVendors.nonce,
    store_id: storeID
  }, function(resp){
    if (resp.success) {
      alert(resp.data.message);
      location.reload();
    } else {
      alert("❌ Fehler: " + (resp.data?.message || "Unbekannt"));
    }
  });
});

});
