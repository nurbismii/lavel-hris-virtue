"""Optional standalone WSGI entry point; do not overwrite the existing app.py."""
from flask import Flask
from passive_verifier import passive_blueprint

app = Flask(__name__)
app.register_blueprint(passive_blueprint)
application = app
