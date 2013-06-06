# -*- coding: utf-8 -*-
from __future__ import with_statement
import sqlite3
import re
from contextlib import closing
from flask import Flask, request, session, g, redirect, url_for, abort, \
     render_template, flash

DATABASE = 'flaskr.db'
DEBUG = True
SECRET_KEY = 'afaQWFEfwfe'
USERNAME = 'admin'
PASSWORD = 'default'

app = Flask(__name__)
app.config.from_object(__name__)
app.config.from_envvar('FLASKR_SETTINGS', silent=True)


def connect_db():
    return sqlite3.connect(app.config['DATABASE'])


def init_db():
    with closing(connect_db()) as db:
        with app.open_resource('schema.sql') as f:
            db.cursor().executescript(f.read())
        db.commit()


@app.before_request
def before_request():
    g.db = connect_db()


@app.after_request
def after_request(response):
    g.db.close()
    return response


@app.route('/')
def show_entries():
    cur = g.db.execute('select title, text, trumpas from entries order by id desc')
    entries = [dict(title=row[0], text=row[1], nr=row[2]) for row in cur.fetchall()]
    return render_template('show_entries.html', entries=entries)

@app.route('/prideti')
def prideti():
    return render_template('forma.html')
    
@app.route('/add', methods=['POST'])
def add_entry():
    slug = request.form['title']
    slug = slug.encode('ascii', 'ignore').lower()
    slug = re.sub(r'[^a-z0-9]+', '-', slug).strip('-')
    trumpas = re.sub(r'[-]+', '-', slug)
    g.db.execute('insert into entries (trumpas, title, text) values (?, ?, ?)',
                 [trumpas, request.form['title'], request.form['text']])
    g.db.commit()
    flash('New entry was successfully posted')
    return redirect(url_for('show_entries'))

@app.route('/puslapis/<p_id>')
def puslapis(p_id):
    cur = g.db.execute('select title, text from entries where trumpas = ?', [p_id])
    entries = [dict(title=row[0], text=row[1]) for row in cur.fetchall()]
    return render_template('page.html', entries=entries)

if __name__ == '__main__':
    app.run()