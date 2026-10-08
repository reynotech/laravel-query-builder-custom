#!/usr/bin/env bash
set -euo pipefail

usage() {
  echo "Usage: $0 <major|minor|patch> [--dry-run] [--no-push]" >&2
}

fail() {
  echo "Error: $*" >&2
  exit 1
}

if [[ $# -eq 0 ]]; then
  usage
  exit 1
fi
if [[ "$1" == '--help' || "$1" == '-h' ]]; then
  usage
  exit 0
fi

level="$1"
shift
case "$level" in
  major|minor|patch) ;;
  *) usage; exit 1 ;;
esac

dry_run=false
push=true
remote="${REMOTE:-origin}"
for arg in "$@"; do
  case "$arg" in
    --dry-run) dry_run=true ;;
    --no-push) push=false ;;
    *) usage; exit 1 ;;
  esac
done

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$script_dir/.."
git rev-parse --is-inside-work-tree >/dev/null
branch="$(git symbolic-ref --quiet --short HEAD)" || fail 'Check out a branch before releasing.'
git diff --cached --quiet || fail 'Staged changes exist. Commit or unstage them first.'

if [[ "$dry_run" == false ]]; then
  command -v composer >/dev/null || fail 'Composer is required.'
  command -v php >/dev/null || fail 'PHP is required.'
  [[ -f vendor/bin/phpunit ]] || fail 'Install dev dependencies with composer install first.'
  if [[ "$push" == true ]]; then
    git remote get-url "$remote" >/dev/null || fail "Remote $remote is not configured."
    git fetch "$remote" --tags
    if git show-ref --verify --quiet "refs/remotes/$remote/$branch"; then
      git merge-base --is-ancestor "refs/remotes/$remote/$branch" HEAD \
        || fail "Branch $branch is behind or diverged from $remote/$branch."
    fi
  fi
fi

# Composer infers the package version from Git tags; composer.json needs no version field.
latest_tag='v0.0.0'
while IFS= read -r tag; do
  if [[ "$tag" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
    latest_tag="$tag"
    break
  fi
done < <(git tag --list 'v*' --sort=-version:refname)

IFS='.' read -r major minor patch <<< "${latest_tag#v}"
case "$level" in
  major) major=$((major + 1)); minor=0; patch=0 ;;
  minor) minor=$((minor + 1)); patch=0 ;;
  patch) patch=$((patch + 1)) ;;
esac
new_tag="v$major.$minor.$patch"
git show-ref --verify --quiet "refs/tags/$new_tag" && fail "Tag $new_tag already exists."

echo "Bumping $latest_tag -> $new_tag ($level)"
if [[ "$dry_run" == true ]]; then
  echo '[dry-run] Would validate Composer and run PHPUnit without changing its result cache.'
  echo '[dry-run] Would commit pending changes, excluding test cache and local agent files.'
  git status --short -- . ':(exclude).phpunit.result.cache' ':(exclude).claude' ':(exclude).codex' ':(exclude).agents'
  echo "[dry-run] Would create annotated tag $new_tag."
  if [[ "$push" == true ]]; then
    echo "[dry-run] Would fetch tags and atomically push $branch and $new_tag to $remote."
  fi
  exit 0
fi

composer validate --no-check-lock --no-interaction
php vendor/bin/phpunit --do-not-cache-result

git add -A -- . ':(exclude).phpunit.result.cache' ':(exclude).claude' ':(exclude).codex' ':(exclude).agents'
if ! git diff --cached --quiet; then
  git diff --cached --check
  git commit -m "Release $new_tag"
fi
git tag -a "$new_tag" -m "Release $new_tag"

if [[ "$push" == true ]]; then
  git push --atomic "$remote" "HEAD:refs/heads/$branch" "refs/tags/$new_tag"
fi
echo "Done: $new_tag"
