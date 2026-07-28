<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manufacturing ERP System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Font - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #1a2332;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: #0d1624;
            border-bottom: 1px solid #2a3a4a;
        }
        
        #sidebar .sidebar-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #fff;
        }
        
        #sidebar .sidebar-header small {
            font-size: 12px;
            color: #8a9aa8;
        }
        
        #sidebar .sidebar-nav {
            padding: 10px 0;
        }
        
        #sidebar .sidebar-nav .nav-item {
            padding: 8px 20px;
            transition: all 0.3s;
        }
        
        #sidebar .sidebar-nav .nav-item:hover {
            background: #2a3a4a;
        }
        
        #sidebar .sidebar-nav .nav-item a {
            color: #b8c7d6;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        #sidebar .sidebar-nav .nav-item a i {
            width: 20px;
            font-size: 16px;
        }
        
        #sidebar .sidebar-nav .nav-item.active {
            background: #2a3a4a;
        }
        
        #sidebar .sidebar-nav .nav-item.active a {
            color: #fff;
        }
        
        #sidebar .sidebar-nav .nav-item .sub-menu {
            padding-left: 32px;
            margin-top: 8px;
        }
        
        #sidebar .sidebar-nav .nav-item .sub-menu a {
            font-size: 13px;
            padding: 5px 0;
        }
        
        #sidebar .sidebar-nav .nav-item .sub-menu .sub-group-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #6c8aa8;
            padding: 8px 5px 2px 5px;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        #content {
            width: 100%;
            padding: 20px;
            margin-left: 250px;
            transition: all 0.3s;
        }
        
        .navbar-custom {
            background: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .navbar-custom .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-custom .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1a2332;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e8eaed;
            padding: 15px 20px;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn-primary {
            background: #1a2332;
            border-color: #1a2332;
        }
        
        .btn-primary:hover {
            background: #0d1624;
            border-color: #0d1624;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            color: #1a2332;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-partial {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #1a2332;
        }
        
        .stat-card .stat-icon {
            font-size: 32px;
            color: #1a2332;
            opacity: 0.5;
        }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            color: #6c757d;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 14px;
            color: #1a2332;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #1a2332;
            box-shadow: 0 0 0 0.2rem rgba(26, 35, 50, 0.1);
        }
        
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            #sidebar.active {
                margin-left: 0;
            }
            #content {
                margin-left: 0;
            }
            #content.active {
                margin-left: 250px;
            }
        }
    </style>

    <!-- jQuery (must load before inline scripts) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
<div class="wrapper">