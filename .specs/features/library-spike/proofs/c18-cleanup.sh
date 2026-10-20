#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c18
clone=${SPIKE_CLONE_DIR:-/private/tmp/claude-501/-Users-helterdias-Documents-POC-laravel-module-ecomerce/afb4558a-fb34-4ffd-933d-00ead1b9878e/scratchpad/spike-clone}
if [ -d "$SPIKE" ]; then
  make -C "$SPIKE" test-db-drop >/dev/null 2>&1 || say "note: test database drop failed or was not needed"
  git -C "$ROOT" worktree remove --force "$SPIKE" || fail "could not remove the worktree"
fi
git -C "$ROOT" branch -D spike/hel-12-library >/dev/null 2>&1
rm -rf "$clone"
git -C "$ROOT" worktree prune
! git -C "$ROOT" worktree list | grep -q 'spike-hel-12' || fail "the spike worktree is still listed"
! git -C "$ROOT" rev-parse --verify -q refs/heads/spike/hel-12-library >/dev/null || fail "the spike branch still exists"
head=$(git -C "$ROOT" rev-parse --short main); say "main HEAD: $head"
[ "$head" = "$BASE" ] || fail "main moved: $head != $BASE"
say "OK"
