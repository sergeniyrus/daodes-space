@extends('template')

@section('title_page')
    {{ __('login.title_page') }}
@endsection

@section('main')
<style>
.input_row{background:#000;color:#fff;border:1px solid #a0ff08;border-radius:5px;width:100%;padding:10px;margin:10px 0;box-sizing:border-box}
.input_row::placeholder{color:#ccc}
.button-container{display:flex;justify-content:center;align-items:center;margin:20px 0}
.link-buttons{display:flex;justify-content:center;gap:20px;margin-top:20px}
.link-buttons a,.link-buttons a img{height:100%}
.error-message{color:red;margin-top:5px;text-align:center}
label{display:block;margin:10px 0 5px;color:#fff}
.submit-button{background:none;border:0;cursor:pointer;padding:0}
.password-container{position:relative;width:100%}
.toggle-password{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#a0ff08;z-index:2;background:none;border:0;padding:0;font-size:16px}
.toggle-password:focus{outline:none}
.auth-session-status{margin-bottom:20px;color:#a0ff08;text-align:center}
.regwin{background:#0b0c18;border:1px solid gold;border-radius:20px;padding:20px;max-width:90%;min-width:280px;margin:20px auto;text-align:center}
.des-btn{display:inline-block;color:#000;font-size:xx-large;background:none;border:1px solid gold;border-radius:10px;box-shadow:none;transition:transform .3s ease}
.des-btn:hover{transform:scale(1.05);box-shadow:0 0 20px #d7fc09,0 0 40px #d7fc09,0 0 60px #d7fc09}
.likebtn{background:none}
</style>
<div class="header">
    <img src="/img/main/img_avatar.jpg" alt="Avatar" class="avatar">
    <div class="regwin">
        <div class="form-container">
            @if (session('status'))
                <div class="auth-session-status">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div>
                    <label for="name">{{ __('login.username') }}</label>
                    <input id="name" class="input_row" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="username" placeholder="{{ __('login.username_placeholder') }}" />
                    @if ($errors->has('name')) <div class="error-message">{{ $errors->first('name') }}</div> @endif
                </div>
                <div class="mt-4">
                    <label for="password">{{ __('login.password') }}</label>
                    <div class="password-container">
                        <input id="password" class="input_row" type="password" name="password" required autocomplete="current-password" placeholder="{{ __('login.password_placeholder') }}" />
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Показать или скрыть пароль">👁️</button>
                    </div>
                    @if ($errors->has('password')) <div class="error-message">{{ $errors->first('password') }}</div> @endif
                </div>
                <br>
                <div class="button-container">
                    <button type="submit" class="submit-button" title="{{ __('login.login_button') }}">
                        <img src="/img/bottom/login.png" alt="{{ __('login.login_button') }}" class="des-btn">
                    </button>
                </div>
                <br>
                <div class="link-buttons">
                    <a href="{{ route('password.request') }}" title="{{ __('login.forgot_password') }}" class="likebtn">
                        <img src="/img/bottom/forgot2.png" alt="{{ __('login.forgot_password') }}" class="des-btn">
                    </a>
                    <a href="{{ route('register') }}" title="{{ __('login.register') }}" class="likebtn">
                        <img src="/img/bottom/registrat.png" alt="{{ __('login.register') }}" class="des-btn">
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
}
</script>
@endsection