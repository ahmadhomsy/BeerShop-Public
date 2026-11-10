let sellBtn = document.querySelector(".seller");
let buybtn = document.querySelector(".buyer");
let adminbtn = document.querySelector(".admin");

function removeFromAll() {
  sellBtn.classList.remove("active");
  buybtn.classList.remove("active");
  adminbtn.classList.remove("active");
}



sellBtn.addEventListener("click", function(){
  removeFromAll();
  sellBtn.classList.add("active");
});
buybtn.addEventListener("click", function(){
  removeFromAll();
  buybtn.classList.add("active");
});
adminbtn.addEventListener("click", function(){
  removeFromAll();
  adminbtn.classList.add("active");
});


let sellComp = document.querySelector(".sellerComp");
let buyComp = document.querySelector(".buyerComp");
let adminComp = document.querySelector(".adminComp");

function removeFromAllShow() {
  sellComp.classList.remove("show");
  buyComp.classList.remove("show");
  adminComp.classList.remove("show");
}

sellBtn.addEventListener("click", function(){
  removeFromAllShow();
  sellComp.classList.add("show");
});
buybtn.addEventListener("click", function(){
  removeFromAllShow();
  buyComp.classList.add("show");
});
adminbtn.addEventListener("click", function(){
  removeFromAllShow();
  adminComp.classList.add("show");
});











