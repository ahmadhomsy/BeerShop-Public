let bars = document.querySelector(".top-bar .left-box .list i");
let mainlist = document.querySelector(" nav .left-list");

bars.addEventListener("mouseenter", function(){
  mainlist.classList.add("active");
  bars.classList.add("active");
});


mainlist.addEventListener("mouseenter", function(){
  mainlist.classList.add("active");
  mainlist.classList.add("f");
  bars.classList.add("active");
});
mainlist.addEventListener("mouseleave", function(){
  mainlist.classList.remove("f");
  mainlist.classList.remove("active");
  bars.classList.remove("active");
});





const menuItems = document.querySelectorAll('.menu > li');

menuItems.forEach(item => {
  const submenu = item.querySelector('.sub');
  if (submenu) {
    item.addEventListener('mouseenter', () => {
      submenu.style.display = 'block';
    });

    item.addEventListener('mouseleave', () => {
      submenu.style.display = 'none';
    });
  }
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














///////////////////////////////////////////////////////////////
function myFunction() {
  // Declare variables
  var input, filter, ul, li, a, i, txtValue;
  input = document.getElementById('myInput');
  filter = input.value.toUpperCase();
  ul = document.getElementById("myUL");
  li = ul.getElementsByTagName('li');


  // Loop through all list items, and hide those who don't match the search query
  for (i = 0; i < li.length; i++) {
    a = li[i].getElementsByTagName("a")[0];
    txtValue = a.textContent || a.innerText;
    if (txtValue.toUpperCase().indexOf(filter) > -1) {
      li[i].style.display = "";
    } else {
      li[i].style.display = "none";
    }
  }
}

let input = document.querySelector('.myInput');
let ul = document.querySelector(".myUL");

input.addEventListener("focusin", function(){
  ul.classList.add("active");
});

input.addEventListener("focusout", function(){
  setTimeout(  function() {ul.classList.remove("active");}
  , 300);
});




///////////////////////////////////////////////////////////////
let slideIndex = 0;
showSlides();

function showSlides() {
  let i;
  let slids = document.getElementsByClassName("mySlides");
  for (i = 0; i < slids.length; i++) {
    slids[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slids.length) {slideIndex = 1}
  slids[slideIndex-1].style.display = "block";
  setTimeout(showSlides, 2500);
};


///////////////////////////////////////////////////////////////

// Show the loader on page load or refresh
// Trigger the loader when the page starts loading
window.addEventListener("beforeunload", showLoader);

// Hide the loader when the page finishes loading
window.addEventListener("load", hideLoader);

  // Show the loader before making an AJAX request
  function showLoader() {
    document.getElementById('loader').style.display = 'block';
  }

  // Hide the loader after the request is complete
  function hideLoader() {
    document.getElementById('loader').style.display = 'none';
  }


