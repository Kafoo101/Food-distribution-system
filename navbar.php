<nav class="navbar">
  <div class="navbar-left">
    <a href="index.php" class="nav-btn">Home</a>
    <a href="purchase.php" class="nav-btn">Purchase</a>
    <a href="sell.php" class="nav-btn">Sell</a>
    <a href="item.php" class="nav-btn">Stock</a>
  </div>
  <div class="navbar-right">
    <a href="login.php" class="nav-btn login-btn">Login</a>
  </div>
</nav>
<style>
.navbar {
  width: 100%;
  background: #a4c4a1ff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 32px;
  height: 56px;
  box-sizing: border-box;
  box-shadow: 0 2px 4px rgba(0,0,0,0.03);
  position: relative;
  z-index: 10;
}
.navbar-left, .navbar-right {
  display: flex;
  align-items: center;
}
.nav-btn {
  display: inline-block;
  color: #333;
  text-decoration: none;
  font-size: 1.1rem;
  padding: 8px 18px;
  border-radius: 4px;
  margin-right: 6px;
  transition: background 0.2s, color 0.2s;
  font-weight: bold;
}
.nav-btn:last-child {
  margin-right: 0;
}
.nav-btn:hover, .nav-btn:focus {
  background: #8bb07d;
  color: #111;
}
.login-btn {
  margin-left: 12px;
  background: #7fa36b;
  color: #fff;
  font-weight: 500;
}
.login-btn:hover, .login-btn:focus {
  background: #6b8c5a;
  color: #fff;
}
</style>
