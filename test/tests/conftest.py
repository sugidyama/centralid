import os
import pytest
import httpx
from dotenv import load_dotenv

load_dotenv()

BASE_URL = os.getenv("BASE_URL", "https://centralid.win")


@pytest.fixture(scope="session")
def client() -> httpx.Client:
    """HTTPクライアント（セッション全体で共有）"""
    with httpx.Client(base_url=BASE_URL, timeout=10) as c:
        yield c


@pytest.fixture(scope="session")
def access_token() -> str:
    """テスト用アクセストークン（.envのTEST_ACCESS_TOKENから取得）"""
    token = os.getenv("TEST_ACCESS_TOKEN", "")
    if not token:
        pytest.skip("TEST_ACCESS_TOKEN が .env に未設定のためスキップ")
    return token


@pytest.fixture(scope="session")
def refresh_token() -> str:
    """テスト用リフレッシュトークン（.envのTEST_REFRESH_TOKENから取得）"""
    token = os.getenv("TEST_REFRESH_TOKEN", "")
    if not token:
        pytest.skip("TEST_REFRESH_TOKEN が .env に未設定のためスキップ")
    return token


@pytest.fixture(scope="session")
def auth_headers(access_token: str) -> dict:
    """Bearerトークン付きヘッダー"""
    return {"Authorization": f"Bearer {access_token}"}
