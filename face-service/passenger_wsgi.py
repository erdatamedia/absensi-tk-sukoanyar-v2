"""
Entry point for cPanel's "Setup Python App" (Passenger).

Passenger's Python support expects a WSGI callable named `application`
in this file. FastAPI is ASGI, so we bridge it with a2wsgi. This only
matters for cPanel deployment — running locally with `uvicorn main:app`
does not use this file at all.
"""

from a2wsgi import ASGIMiddleware

from main import app as fastapi_app

application = ASGIMiddleware(fastapi_app)
