import pytest
import httpx
from schemas.user import UserResponse, TokenRefreshResponse, ErrorResponse


class TestTokenVerify:
    """GET /auth/token — トークン検証・ユーザー情報取得テスト"""

    def test_valid_token_returns_user(self, client: httpx.Client, auth_headers: dict):
        """有効なトークンで200とユーザー情報が返る"""
        res = client.get("/auth/token", headers=auth_headers)
        assert res.status_code == 200
        body = UserResponse.model_validate(res.json())
        assert body.success is True
        assert body.data.central_id is not None
        assert body.data.public_id > 0

    def test_no_token_returns_401(self, client: httpx.Client):
        """トークン未提供で401エラー"""
        res = client.get("/auth/token")
        assert res.status_code == 401
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code in ("UNAUTHORIZED", "TOKEN_EXPIRED")

    def test_invalid_token_returns_401(self, client: httpx.Client):
        """無効なトークンで401エラー"""
        res = client.get("/auth/token", headers={"Authorization": "Bearer invalid_token"})
        assert res.status_code == 401
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code in ("UNAUTHORIZED", "TOKEN_EXPIRED")

    def test_malformed_bearer_returns_401(self, client: httpx.Client):
        """Bearer形式でないヘッダーで401エラー"""
        res = client.get("/auth/token", headers={"Authorization": "Token something"})
        assert res.status_code == 401


class TestTokenRefresh:
    """PATCH /auth/token — アクセストークン更新テスト"""

    def test_valid_refresh_token_returns_new_tokens(self, client: httpx.Client, refresh_token: str):
        """有効なリフレッシュトークンで新しいトークンペアが返る"""
        res = client.patch("/auth/token", json={"refresh_token": refresh_token})
        assert res.status_code == 200
        body = TokenRefreshResponse.model_validate(res.json())
        assert body.success is True
        assert body.data.access_token != ""
        assert body.data.refresh_token != ""
        assert body.data.expires_in == 900

    def test_invalid_refresh_token_returns_401(self, client: httpx.Client):
        """無効なリフレッシュトークンで401エラー"""
        res = client.patch("/auth/token", json={"refresh_token": "invalid_refresh_token"})
        assert res.status_code == 401
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code in ("UNAUTHORIZED", "TOKEN_EXPIRED")

    def test_missing_refresh_token_returns_401(self, client: httpx.Client):
        """refresh_token未指定で401エラー"""
        res = client.patch("/auth/token", json={})
        assert res.status_code == 401
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False


class TestLogout:
    """DELETE /auth/token — ログアウトテスト"""

    def test_valid_token_returns_204(self, client: httpx.Client, auth_headers: dict):
        """有効なトークンで204が返る"""
        res = client.delete("/auth/token", headers=auth_headers)
        assert res.status_code == 204

    def test_no_token_returns_401(self, client: httpx.Client):
        """トークン未提供で401エラー"""
        res = client.delete("/auth/token")
        assert res.status_code == 401
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "UNAUTHORIZED"

    def test_invalid_token_returns_401(self, client: httpx.Client):
        """無効なトークンで401エラー"""
        res = client.delete("/auth/token", headers={"Authorization": "Bearer invalid"})
        assert res.status_code == 401
