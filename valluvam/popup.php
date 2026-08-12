<!-- POPUP FORM CONTAINER -->
<div class="form-modal" id="form-modal" style="display: none;">
	<div class="form-toggle">
		<button id="login-toggle" onclick="toggleLogin()">log in</button>
		<button id="signup-toggle" onclick="toggleSignup()">sign up</button>
		<button class="close-btn" onclick="closeForm()">X</button>
	</div>

	<!-- LOGIN FORM -->
	<div id="login-form">
		<form id="loginForm">
			<input type="text" id="login-username" placeholder="Enter email or username" required />
			<input type="password" id="login-password" placeholder="Enter password" required />
			<button type="submit" class="btn login">Login</button>
			<!-- <p><a href="#">Forgotten account?</a></p> -->
			<hr />
		</form>
	</div>

	<!-- SIGNUP FORM -->
	<div id="signup-form" style="display: none;">
		<form id="signupForm">
			<input type="email" id="signup-email" placeholder="Enter your email" required />
			<input type="text" id="signup-phone" placeholder="Enter your contact number" required />
			<input type="text" id="signup-username" placeholder="Choose username" required />
			<input type="password" id="signup-password" placeholder="Create password" required />
			<button type="submit" class="btn signup">Create Account</button>
			<p>By signing up, you agree to our <a href="#">terms of services</a>.</p>
			<hr />
		</form>
	</div>
</div>
