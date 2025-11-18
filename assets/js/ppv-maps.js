document.addEventListener("DOMContentLoaded", function(){
  var mapEl = document.getElementById("ppv-map");
  if (!mapEl) return;

  var lat = parseFloat(mapEl.dataset.lat);
  var lng = parseFloat(mapEl.dataset.lng);
  var title = mapEl.dataset.title;

  var map = new google.maps.Map(mapEl, {
    center: {lat: lat, lng: lng},
    zoom: 15
  });

  new google.maps.Marker({
    position: {lat: lat, lng: lng},
    map: map,
    title: title
  });
});
