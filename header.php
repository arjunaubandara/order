<?php
// File: /Production/header.php
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .nav-container {
            background-color: #000;
            color: #0f0;
            padding: 5px 10px;
            border-bottom: 1px solid #0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-title {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            margin: 0;
            color: #0f0;
        }
        
        .nav-buttons a {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 3px 10px;
            margin-left: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .nav-buttons a:hover {
            background-color: #0f0;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="nav-container">
        <h2 class="nav-title">PRODUCTION SYSTEM</h2>
        <div class="nav-buttons">
            <a href="/Production/index.php">HOME</a>
            <a href="javascript:history.back()">BACK</a>
        </div>
    </div>
    <!-- Page content starts below -->