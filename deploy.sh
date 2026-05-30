#!/bin/bash
# ==============================================================================
# Deployment Script (Executed automatically by CircleCI via SSH)
# ==============================================================================
# 【目的】
#   CircleCIから呼び出され、本番環境のソースコードを最新化します。
# 【前提条件】
#   1. GitHubへのコードプッシュを検知してCircleCIが起動し、VPSへSSH接続します。
#   2. VPS側でSSH接続時に実行できるコマンドを本スクリプトのみに制限し、自動デプロイを実現します。
# ==============================================================================

set -e

# スクリプト自身の配置場所から、プロジェクトのルートパスを自動で特定
PROJECT_DIR=$(cd $(dirname $0); pwd)
BRANCH_NAME="main"

echo "=================================================="
echo " 🚀 Deployment Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo " Target Directory: ${PROJECT_DIR}"
echo "=================================================="

# 1. プロジェクトのディレクトリに移動
cd "${PROJECT_DIR}"

# 2. リポジトリから最新のソースコードを取得
echo "➔ Fetching the latest code from GitHub..."
git checkout "${BRANCH_NAME}"
git pull origin "${BRANCH_NAME}"

# 3. ファイル所有権・権限の適正化
echo "➔ Optimizing file permissions..."
# chown -R nginx:nginx .
# chmod -R 755 .

# ------------------------------------------------------------------------------
# 4. アプリケーション固有のビルドやキャッシュクリア（必要に応じてコメントアウトを解除）
# ------------------------------------------------------------------------------
echo "➔ Running post-deployment optimization tasks..."

cd ./backend
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=================================================="
echo " 🎉 Deployment Successfully Completed!"
echo "=================================================="