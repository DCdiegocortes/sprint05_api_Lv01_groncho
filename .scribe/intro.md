# Introduction

REST API for Grôncho, a money-free clothing swap app.

<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>

Swipe on people, match, and exchange garments. Every endpoint except register and login requires a token.

Get a token with `POST /api/login` and send it in every request as `Authorization: Bearer {token}`.
Send `Accept: application/json` so errors are returned as JSON.

