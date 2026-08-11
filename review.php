<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Valluvam</title>
  <style>
/* .content-wrapper {
	height: 25%;
	width: 100%;
	max-width: 100rem;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	padding-bottom: 5rem;
} */
.blue-line {
	height: 0.3rem;
	width: 6rem;
	background-color: #28AE28;
	margin-bottom: calc(3rem + 2vmin);
}

.wrapper-for-arrows {
	position: relative;
	width: 70%;
	border-radius: 2rem;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	overflow: hidden;
	display: grid;
	place-items: center;
}

.review-wrap {
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	padding-top: calc(2rem + 1vmin);
	width: 100%;
}

#imgDiv {
	border-radius: 50%;
	width: calc(6rem + 4vmin);
	height: calc(6rem + 4vmin);
	position: relative;
	box-shadow: 5px -3px #28AE46;
	background-size: cover;
	margin-bottom: calc(0.7rem + 0.5vmin);
}

.chicken {
	background-image: url("https://media0.giphy.com/media/A8Cdznswn5vnG/200w.gif?cid=790b7611e8c5980ee7141bc18ec12c49962b871eb404ba5b&rid=200w.gif&ct=s");
	width: 200px;
	height: 250px;
	position: absolute;
	top: 12%;
}

#imgDiv::after {
	content: "''";
	font-size: calc(2rem + 2vmin);
	font-family: sans-serif;
	line-height: 150%;
	color: #fff;
	display: grid;
	place-items: center;
	border-radius: 50%;
	background-color: #28AE28;
	position: absolute;
	top: 10%;
	left: -10%;
	width: calc(2rem + 2vmin);
	height: calc(2rem + 2vmin);
}

#personName {
	margin-bottom: calc(0.7rem + 0.5vmin);
	font-size: calc(1rem + 0.5vmin);
	letter-spacing: calc(0.1rem + 0.1vmin);
	font-weight: bold;
}

#user {
	font-size: calc(0.8rem + 0.3vmin);
	margin-bottom: calc(0.7rem + 0.5vmin);
	color: #28AE28;
}

#description {
	font-size: calc(0.8rem + 0.3vmin);
	width: 70%;
	max-width: 40rem;
	text-align: center;
	margin-bottom: calc(1.4rem + 1vmin);
	color: rgb(92, 92, 92);
	line-height: 2rem;
}

.arrow-wrap {
	position: absolute;
	top: 50%;
}

.arrow {
	width: calc(1.4rem + 0.6vmin);
	height: calc(1.4rem + 0.6vmin);
	border: solid #28AE28;
	border-width: 0 calc(0.5rem + 0.2vmin) calc(0.5rem + 0.2vmin) 0;
	cursor: pointer;
	transition: transform 0.3s;
}

.arrow:hover {
	transition: 0.3s;
	transform: scale(1.15);
}

.left-arrow-wrap {
	left: 5%;
	transform: rotate(135deg);
	-webkit-transform: rotate(135deg);
}

.right-arrow-wrap {
	transform: rotate(-45deg);
	-webkit-transform: rotate(-45deg);
	right: 5%;
}
.move-head {
	animation: moveHead 1.55s infinite;
	animation-delay: -0.8s;
}
@keyframes moveHead {
	0% {
	}
	25% {
		transform: translate(0.5rem, 1rem) rotate(5deg);
	}
	100% {
		transform: translate(0, 0) rotate(-5deg);
	}
}

@media screen and (max-width: 900px) {
	.content-wrapper {
		width: 99.94%;
	}
}

  </style>
</head>
<body>
	<div class="content-wrapper" style="width: 99.94%;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	
">
		<h1>Our <span style="color: #82ae46;">Reviews</span></h1>
		<div class="blue-line"></div>
		<div class="wrapper-for-arrows">
			<div style="opacity: 0;" class="chicken"></div>
			<div id="reviewWrap" class="review-wrap">
				<div id="imgDiv" class="">
				</div>
				<div id="personName"></div>
				<div id="user"></div>
				<div id="description">
				</div>
			</div>
			<!-- <div id="surpriseMeBtn" class="surprise-me-btn">Surprise me</div> -->
			<div class="left-arrow-wrap arrow-wrap">
				<div class="arrow" id="leftArrow"></div>
			</div>
			<div class="right-arrow-wrap arrow-wrap">
				<div class="arrow" id="rightArrow"></div>
			</div>
		</div>
	</div>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
  <script>
    const reviewWrap = document.getElementById("reviewWrap");
const leftArrow = document.getElementById("leftArrow");
const rightArrow = document.getElementById("rightArrow");
const imgDiv = document.getElementById("imgDiv");
const personName = document.getElementById("personName");
const profession = document.getElementById("user");
const description = document.getElementById("description");
const surpriseMeBtn = document.getElementById("surpriseMeBtn");
const chicken = document.querySelector(".chicken");

let isChickenVisible;

let people = [
	{
		photo:
			'url("images/customer-1.jpeg")',
		name: "Nirmal",
		user: "Customer",
		description:
			" Best place for pure cold-pressed coconut oil and fresh nuts! Quick delivery and friendly service"
	},

	{
		photo:
			"url('images/customer-2.jpeg')",
		name: "Pari",
		user: "customer",
		description:
			"Pure sesame and groundnut oil with amazing aroma! Wholesale rates are the best. Highly satisfied"
	},

	{
		photo:
			"url('images/customer-3.jpeg')",
		name: "santhosh",
		user: "customer",
		description:
			" Best quality sesame oil and dry fruits! Reliable delivery and good wholesale pricing"
	},

	{
		photo:
			"url('images/customer-4.jpeg')",
		name: "Sanjay",
		user: "customer",
		description:
			" A one-stop shop for millets, nuts, and cold-pressed oils. Great customer service with quick delivery"
	}
];

imgDiv.style.backgroundImage = people[0].photo;
personName.innerText = people[0].name;
profession.innerText = people[0].user;
description.innerText = people[0].description;
let currentPerson = 0;

//Select the side where you want to slide
function slide(whichSide, personNumber) {
	let reviewWrapWidth = reviewWrap.offsetWidth + "px";
	let descriptionHeight = description.offsetHeight + "px";
	//(+ or -)
	let side1symbol = whichSide === "left" ? "" : "-";
	let side2symbol = whichSide === "left" ? "-" : "";

	let tl = gsap.timeline();

	if (isChickenVisible) {
		tl.to(chicken, {
			duration: 0.4,
			opacity: 0
		});
	}

	tl.to(reviewWrap, {
		duration: 0.4,
		opacity: 0,
		translateX: `${side1symbol + reviewWrapWidth}`
	});

	tl.to(reviewWrap, {
		duration: 0,
		translateX: `${side2symbol + reviewWrapWidth}`
	});

	setTimeout(() => {
		imgDiv.style.backgroundImage = people[personNumber].photo;
	}, 400);
	setTimeout(() => {
		description.style.height = descriptionHeight;
	}, 400);
	setTimeout(() => {
		personName.innerText = people[personNumber].name;
	}, 400);
	setTimeout(() => {
		user.innerText = people[personNumber].user;
	}, 400);
	setTimeout(() => {
		description.innerText = people[personNumber].description;
	}, 400);

	tl.to(reviewWrap, {
		duration: 0.4,
		opacity: 1,
		translateX: 0
	});

	if (isChickenVisible) {
		tl.to(chicken, {
			duration: 0.4,
			opacity: 1
		});
	}
}

function setNextCardLeft() {
	if (currentPerson === 3) {
		currentPerson = 0;
		slide("left", currentPerson);
	} else {
		currentPerson++;
	}

	slide("left", currentPerson);
}

function setNextCardRight() {
	if (currentPerson === 0) {
		currentPerson = 3;
		slide("right", currentPerson);
	} else {
		currentPerson--;
	}

	slide("right", currentPerson);
}

leftArrow.addEventListener("click", setNextCardLeft);
rightArrow.addEventListener("click", setNextCardRight);

window.addEventListener("resize", () => {
	description.style.height = "100%";
});

  </script>
</body>
  
</body>
</html>