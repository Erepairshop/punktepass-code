jQuery(document).ready(function($){
  const notice = $('.ppv-notice.success');
  if(notice.length){
    // konfetti animáció
    const duration = 3 * 1000;
    const animationEnd = Date.now() + duration;
    const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999 };

    function randomInRange(min, max) {
      return Math.random() * (max - min) + min;
    }

    const interval = setInterval(function() {
      const timeLeft = animationEnd - Date.now();

      if (timeLeft <= 0) {
        return clearInterval(interval);
      }

      const particleCount = 50 * (timeLeft / duration);
      confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0, 1), y: Math.random() - 0.2 } }));
    }, 250);

    // automatikus redirect 5 mp után
    setTimeout(function(){
      const url = new URL(window.location.href);
      url.searchParams.delete('status');
      url.searchParams.delete('msg');
      url.searchParams.delete('scan');
      window.location.href = url.toString();
    }, 5000);
  }
});
