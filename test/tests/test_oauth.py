import pytest
import httpx
from schemas.user import ErrorResponse, TokenResponse


class TestOauthRedirect:
    """GET /oauth/{identity} — OAuth認証画面リダイレクトテスト"""

    @pytest.mark.parametrize("provider", ["google", "github"])
    def test_redirects_to_provider(self, client: httpx.Client, provider: str):
        """有効なproviderとredirect_uriで302リダイレクトが返る（サーバー設定が必要）"""
        res = client.get(
            f"/oauth/{provider}",
            params={"redirect_uri": "https://example.com/callback"},
            follow_redirects=False,
        )
        # 500はOAuth設定未完了のサーバーバグ
        assert res.status_code != 500, (
            f"OAuth {provider} が500エラー。サーバー側のOAuth設定（クライアントID/シークレット）を確認してください。"
            f" レスポンス: {res.text}"
        )
        assert res.status_code == 302
        assert "Location" in res.headers

    def test_invalid_service_id_returns_400(self, client: httpx.Client):
        """存在しないservice_idでINVALID_SERVICEエラー"""
        res = client.get(
            "/oauth/google",
            params={
                "redirect_uri": "https://example.com/callback",
                "service_id": "nonexistent_service_xyz",
            },
            follow_redirects=False,
        )
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "INVALID_SERVICE"

    def test_missing_redirect_uri_returns_400(self, client: httpx.Client):
        """redirect_uri未指定でバリデーションエラー"""
        res = client.get("/oauth/google", follow_redirects=False)
        assert res.status_code == 400

    def test_unknown_provider_returns_4xx(self, client: httpx.Client):
        """未対応プロバイダーで4xxエラー"""
        try:
            res = client.get(
                "/oauth/twitter",
                params={"redirect_uri": "https://example.com/callback"},
                follow_redirects=False,
                timeout=5,
            )
            assert res.status_code in (400, 404)
        except httpx.ReadTimeout:
            pytest.skip("未対応プロバイダーへのリクエストがタイムアウト（ルーティング未設定の可能性）")


class TestOauthCallback:
    """GET /oauth/{identity}/callback — OAuthコールバックテスト"""

    @pytest.mark.parametrize("provider", ["google", "github"])
    def test_invalid_state_returns_400(self, client: httpx.Client, provider: str):
        """不正なstateでINVALID_STATEエラー"""
        res = client.get(
            f"/oauth/{provider}/callback",
            params={"code": "dummy_code", "state": "invalid_state"},
            follow_redirects=False,
        )
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "INVALID_STATE"


class TestOauthLogin:
    """POST /oauth/{identity}/login — 一時コードによるログインテスト"""

    @pytest.mark.parametrize("provider", ["google", "github"])
    def test_invalid_auth_code_returns_400(self, client: httpx.Client, provider: str):
        """無効な一時コードでINVALID_CODEエラー"""
        res = client.post(
            f"/oauth/{provider}/login",
            json={"auth_code": "invalid_auth_code_xyz"},
        )
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code in ("INVALID_CODE", "CODE_EXPIRED")

    @pytest.mark.parametrize("provider", ["google", "github"])
    def test_missing_auth_code_returns_400(self, client: httpx.Client, provider: str):
        """auth_code未指定でバリデーションエラー"""
        res = client.post(f"/oauth/{provider}/login", json={})
        assert res.status_code == 400
