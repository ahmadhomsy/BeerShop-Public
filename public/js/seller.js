let bars = document.querySelector(".top-bar .left-box .list i");
let mainlist = document.querySelector(" nav .left-list");

bars.addEventListener("mouseenter", function(){
  mainlist.classList.add("active");
});


mainlist.addEventListener("mouseenter", function(){
  mainlist.classList.add("active");
  mainlist.classList.add("f");
});
mainlist.addEventListener("mouseleave", function(){
  mainlist.classList.remove("f");
  mainlist.classList.remove("active");
});

function ff() {
    if(mainlist.classList.contains("active")) {
    mainlist.classList.remove("f");
    mainlist.classList.remove("active");
    }
    else {
    mainlist.classList.add("f");
    mainlist.classList.add("active");
    }
}



// Show the loader on page load or refresh
window.addEventListener('load', function() {
    hideLoader();
  });

  // Show the loader before making an AJAX request
  function showLoader() {
    document.getElementById('loader').style.display = 'block';
  }

  // Hide the loader after the request is complete
  function hideLoader() {
    document.getElementById('loader').style.display = 'none';
  }
