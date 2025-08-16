<?php
// Start or resume the current session
// This gives access to existing session variables
session_start();

// Clear all session variables
// Removes all data from the $_SESSION superglobal array
session_unset();

// Destroy the session
// Deletes the session data from the server and invalidates the session ID
session_destroy();

// Redirect the user to the homepage (index.html)
// Using 302 (temporary) redirect by default
header("Location: index.html");

// Ensure no further code is executed after the redirect
// Prevents potential security issues from continued execution
exit();