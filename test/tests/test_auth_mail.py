import pytest
import httpx
from schemas.user import ErrorResponse


class TestOtpIssue:
    """POST /auth/mail — OTP発行テスト"""

    def test_success_returns_204(self, client: httpx.Client):
        """正常なメールアドレスで204が返る"""
        res = client.post("/auth/mail", json={"email": "test@example.com"})
        assert res.status_code == 204

    def test_invalid_email_returns_400(self, client: httpx.Client):
        """不正なメールアドレス形式でバリデーションエラー"""
        res = client.post("/auth/mail", json={"email": "not-an-email"})
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "VALIDATION_ERROR"

    def test_missing_email_returns_400(self, client: httpx.Client):
        """emailフィールド未指定でバリデーションエラー"""
        res = client.post("/auth/mail", json={})
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False

    def test_invalid_service_id_returns_400(self, client: httpx.Client):
        """存在しないservice_idでINVALID_SERVICEエラー"""
        res = client.post("/auth/mail", json={
            "email": "test@example.com",
            "service_id": "nonexistent_service_xyz"
        })
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "INVALID_SERVICE"

    def test_without_service_id_returns_204(self, client: httpx.Client):
        """service_id未指定（省略可能）で204が返る"""
        res = client.post("/auth/mail", json={"email": "test@example.com"})
        assert res.status_code == 204


class TestOtpResend:
    """PUT /auth/mail — OTP再送信テスト"""

    def test_resend_too_soon_returns_400(self, client: httpx.Client):
        """連続送信でRESEND_TOO_SOONエラー"""
        # 1回目送信
        client.post("/auth/mail", json={"email": "test@example.com"})
        # 即時再送信 → 60秒未満なので拒否される
        res = client.put("/auth/mail", json={"email": "test@example.com"})
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "RESEND_TOO_SOON"

    def test_invalid_email_returns_400(self, client: httpx.Client):
        """不正なメールアドレス形式でバリデーションエラー"""
        res = client.put("/auth/mail", json={"email": "bad-email"})
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False

    def test_invalid_service_id_returns_400(self, client: httpx.Client):
        """存在しないservice_idでINVALID_SERVICEエラー"""
        res = client.put("/auth/mail", json={
            "email": "test@example.com",
            "service_id": "nonexistent_service_xyz"
        })
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code == "INVALID_SERVICE"


class TestOtpLogin:
    """POST /auth/mail/login — OTP検証・ログインテスト"""

    def test_invalid_code_returns_400(self, client: httpx.Client):
        """存在しないコードでINVALID_CODEエラー"""
        res = client.post("/auth/mail/login", json={
            "email": "test@example.com",
            "code": "000000"
        })
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False
        assert body.error.code in ("INVALID_CODE", "CODE_EXPIRED", "MAX_ATTEMPTS_EXCEEDED")

    def test_invalid_code_format_returns_400(self, client: httpx.Client):
        """6桁数字以外のコードでバリデーションエラー"""
        res = client.post("/auth/mail/login", json={
            "email": "test@example.com",
            "code": "abc"
        })
        assert res.status_code == 400
        body = ErrorResponse.model_validate(res.json())
        assert body.success is False

    def test_missing_fields_returns_400(self, client: httpx.Client):
        """必須フィールド欠落でバリデーションエラー"""
        res = client.post("/auth/mail/login", json={"email": "test@example.com"})
        assert res.status_code == 400
