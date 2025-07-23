#!/usr/bin/env bash
set -euo pipefail

# Install bundler + jekyll if missing
if ! command -v jekyll >/dev/null; then
  echo "👉 Installing bundler + jekyll…"
  gem install bundler jekyll
fi

# Install gems from your Gemfile
echo "👉 Running bundle install…"
bundle install --local --jobs=4
