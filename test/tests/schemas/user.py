from typing import Optional
from pydantic import BaseModel
from datetime import datetime


class User(BaseModel):
    """ユーザー情報スキーマ"""
    central_id: str
    public_id: int
    user_name: Optional[str]
    display_name: Optional[str]
    created_at: datetime


class TokenData(BaseModel):
    """トークン発行データスキーマ"""
    access_token: str
    refresh_token: str
    expires_in: int
    is_new_user: bool
    user: User


class TokenResponse(BaseModel):
    """トークンレスポンス全体スキーマ"""
    success: bool
    data: TokenData


class TokenRefreshData(BaseModel):
    """トークン更新データスキーマ"""
    access_token: str
    refresh_token: str
    expires_in: int


class TokenRefreshResponse(BaseModel):
    """トークン更新レスポンス全体スキーマ"""
    success: bool
    data: TokenRefreshData


class UserResponse(BaseModel):
    """ユーザー情報取得レスポンス全体スキーマ"""
    success: bool
    data: User


class ErrorDetail(BaseModel):
    """エラー詳細スキーマ"""
    code: str
    message: Optional[str] = None


class ErrorResponse(BaseModel):
    """エラーレスポンス全体スキーマ"""
    success: bool
    error: ErrorDetail
