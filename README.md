# 餐廳候位系統 (Restaurant Queue System)

這是一個用於餐廳管理候位的系統，提供遠端取號、現場取號、候位狀態查詢、黑名單管理和統計數據等功能。

## 系統需求

- PHP 7.4+
- MySQL 5.7+
- Web 伺服器 (Apache/Nginx)
- XAMPP (推薦用於本地開發)

## 安裝說明

1. 將專案檔案複製到 XAMPP 的 htdocs 目錄中
2. 創建資料庫並匯入 `init_database.sql` 檔案
3. 確保 Web 伺服器有適當的權限訪問專案檔案
4. 訪問網站入口開始使用系統

## 網站入口

網站主要入口位於 `public` 目錄下：

```
http://localhost/restaurant-queue-system/public/
```

系統包含以下主要頁面：
- 候位狀態頁面：顯示當前候位號碼和等待人數
- 遠端取號頁面：客戶可以遠端取號
- 現場取號頁面：員工可以為客戶現場取號
- 後台管理頁面：管理候位、查看統計數據和黑名單管理

## API 文檔

本系統提供了 RESTful API，可以通過以下方式查看 API 文檔：

### OpenAPI 規範

完整的 API 規範文件位於：

```
http://localhost/restaurant-queue-system/openapi.yml
```

這是一個符合 OpenAPI 3.0.0 規範的 YAML 文件，描述了所有可用的 API 端點、請求參數、響應格式和安全要求。

### Swagger UI

為了更方便地瀏覽和測試 API，系統提供了 Swagger UI 介面：

```
http://localhost/restaurant-queue-system/swagger-ui.html
```

通過 Swagger UI，您可以：
- 瀏覽所有 API 端點
- 查看請求和響應格式
- 測試 API 功能
- 了解 API 的詳細說明和示例

## 系統架構

系統架構的詳細說明可以在 `system_architecture.md` 文件中找到，包括前端層、API 層和數據庫層的設計。

## 數據庫結構

數據庫結構的詳細說明可以在 `database_schema.dbml` 文件中找到，使用 DBML (Database Markup Language) 格式描述了所有表和關係。

## 主要功能

1. **身份驗證**
   - 員工登入/登出
   - 基於 JWT 的身份驗證

2. **候位管理**
   - 遠端取號（需要手機驗證）
   - 現場取號
   - 候位狀態查詢
   - 叫號和更新狀態

3. **黑名單管理**
   - 查看黑名單客戶
   - 將客戶加入/移出黑名單

4. **統計數據**
   - 候位數據統計
   - 平均等待時間
   - 遠端/現場取號比例

## 開發者資訊

### API 開發

API 實現位於 `api-proxy.php` 文件中，採用 RESTful 設計原則，包括：
- 資源導向的 URL 結構
- 適當的 HTTP 方法使用
- HATEOAS 鏈接
- 適當的 HTTP 狀態碼

### 前端開發

前端實現位於 `public` 目錄下，主要文件包括：
- `index.html`：主頁面結構
- `js/main.js`：主要 JavaScript 邏輯
- `css/style.css`：樣式表
