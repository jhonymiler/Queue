#!/bin/bash
# Compila fontes canônicas do .harness/ para o formato específico de cada IDE.
# Uso: .harness/compile.sh [target]
# Targets: cursor, claude-code, copilot, codex, kiro, all
# Sem argumento: compila apenas targets habilitados no config.json

set -euo pipefail

HARNESS_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$HARNESS_DIR")"
CONFIG="$HARNESS_DIR/config.json"

if ! command -v jq &>/dev/null; then
  echo "ERRO: jq é necessário. Instale com: sudo apt install jq" >&2
  exit 1
fi

target_filter="${1:-}"

is_target_enabled() {
  local target="$1"
  if [[ -n "$target_filter" && "$target_filter" != "all" && "$target_filter" != "$target" ]]; then
    return 1
  fi
  if [[ -n "$target_filter" && "$target_filter" == "$target" ]]; then
    return 0
  fi
  jq -r ".targets.\"$target\".enabled" "$CONFIG" | grep -q "true"
}

compile_cursor() {
  echo ">> Compilando para Cursor..."

  local rules_dir="$PROJECT_ROOT/.cursor/rules"
  local hooks_dir="$PROJECT_ROOT/.cursor/hooks"
  local skills_dir="$PROJECT_ROOT/.cursor/skills"

  mkdir -p "$rules_dir" "$hooks_dir" "$skills_dir"

  for rule_file in "$HARNESS_DIR"/rules/*.md; do
    [[ -f "$rule_file" ]] || continue
    local basename=$(basename "$rule_file" .md)
    local output="$rules_dir/${basename}.mdc"

    local description scope
    description=$(grep '^> description:' "$rule_file" | sed 's/^> description: //' | head -1)
    scope=$(grep '^> scope:' "$rule_file" | sed 's/^> scope: //' | head -1)

    local always_apply="false"
    [[ "$scope" == "always" ]] && always_apply="true"

    local globs=""
    [[ "$scope" != "always" && -n "$scope" ]] && globs="$scope"

    {
      echo "---"
      echo "description: ${description:-Rule gerada pelo harness}"
      [[ -n "$globs" ]] && echo "globs: $globs"
      echo "alwaysApply: $always_apply"
      echo "---"
      echo ""
      grep -v '^> scope:' "$rule_file" | grep -v '^> description:'
    } > "$output"

    echo "   [rule] $basename.md -> $basename.mdc"
  done

  for hook_script in "$HARNESS_DIR"/hooks/*.sh; do
    [[ -f "$hook_script" ]] || continue
    local basename=$(basename "$hook_script")
    cp "$hook_script" "$hooks_dir/$basename"
    chmod +x "$hooks_dir/$basename"
    echo "   [hook] $basename"
  done

  local hooks_json="$PROJECT_ROOT/.cursor/hooks.json"
  jq -n '{
    version: 1,
    hooks: {
      beforeShellExecution: [{
        command: ".cursor/hooks/validate-pr-template.sh",
        matcher: "gh\\s+pr\\s+create",
        failClosed: true,
        timeout: 10
      }],
      preToolUse: [{
        command: ".cursor/hooks/validate-pr-tooluse.sh",
        matcher: "Shell",
        timeout: 10
      }],
      beforeSubmitPrompt: [{
        type: "prompt",
        prompt: "Analise o prompt do usuario. Se ele menciona criar PR, abrir PR, pull request, merge request, ou pedir review de codigo para merge, injete o seguinte contexto: LEMBRETE: Este projeto exige que todo PR siga o template obrigatorio com 5 secoes: Contexto, Objetivo, Criterios de Aceite, Como Testar Manualmente e Cenarios de Teste. Leia a skill pr-reviewer antes de prosseguir. Se o prompt NAO e sobre criacao de PR, ignore.",
        timeout: 10
      }]
    }
  }' > "$hooks_json"
  echo "   [config] hooks.json"

  for skill_file in "$HARNESS_DIR"/skills/*.md; do
    [[ -f "$skill_file" ]] || continue
    local basename=$(basename "$skill_file" .md)
    local skill_dir="$skills_dir/$basename"
    mkdir -p "$skill_dir"
    cp "$skill_file" "$skill_dir/SKILL.md"
    echo "   [skill] $basename -> $basename/SKILL.md"
  done

  echo "   Cursor: OK"
}

compile_claude_code() {
  echo ">> Compilando para Claude Code..."

  local claude_md="$PROJECT_ROOT/CLAUDE.md"
  local skills_dir="$PROJECT_ROOT/.claude/skills"

  mkdir -p "$skills_dir"

  {
    echo "# Instruções do Projeto"
    echo ""
    echo "## Rules"
    echo ""
    for rule_file in "$HARNESS_DIR"/rules/*.md; do
      [[ -f "$rule_file" ]] || continue
      grep -v '^> scope:' "$rule_file" | grep -v '^> description:'
      echo ""
      echo "---"
      echo ""
    done
  } > "$claude_md"
  echo "   [rules] -> CLAUDE.md"

  for skill_file in "$HARNESS_DIR"/skills/*.md; do
    [[ -f "$skill_file" ]] || continue
    local basename=$(basename "$skill_file" .md)
    local skill_dir="$skills_dir/$basename"
    mkdir -p "$skill_dir"
    cp "$skill_file" "$skill_dir/SKILL.md"
    echo "   [skill] $basename -> .claude/skills/$basename/SKILL.md"
  done

  if [[ -d "$HARNESS_DIR/hooks" ]]; then
    local settings="$PROJECT_ROOT/.claude/settings.json"
    mkdir -p "$PROJECT_ROOT/.claude"
    jq -n '{
      hooks: {
        "PreToolUse": [{
          matcher: "Bash",
          hooks: [".harness/hooks/validate-pr-tooluse.sh"]
        }]
      }
    }' > "$settings"
    echo "   [hooks] -> .claude/settings.json"
  fi

  echo "   Claude Code: OK"
}

compile_copilot() {
  echo ">> Compilando para GitHub Copilot..."

  local instructions_dir="$PROJECT_ROOT/.github/instructions"
  mkdir -p "$instructions_dir"

  for rule_file in "$HARNESS_DIR"/rules/*.md; do
    [[ -f "$rule_file" ]] || continue
    local basename=$(basename "$rule_file" .md)
    local output="$instructions_dir/${basename}.instructions.md"

    grep -v '^> scope:' "$rule_file" | grep -v '^> description:' > "$output"
    echo "   [instruction] $basename.md -> ${basename}.instructions.md"
  done

  echo "   Copilot: OK (hooks não suportados nativamente)"
}

compile_codex() {
  echo ">> Compilando para Codex..."

  local agents_md="$PROJECT_ROOT/AGENTS.md"

  {
    echo "# Agent Instructions"
    echo ""
    for rule_file in "$HARNESS_DIR"/rules/*.md; do
      [[ -f "$rule_file" ]] || continue
      grep -v '^> scope:' "$rule_file" | grep -v '^> description:'
      echo ""
      echo "---"
      echo ""
    done

    for skill_file in "$HARNESS_DIR"/skills/*.md; do
      [[ -f "$skill_file" ]] || continue
      grep -v '^> description:' "$skill_file"
      echo ""
      echo "---"
      echo ""
    done
  } > "$agents_md"
  echo "   [all] -> AGENTS.md"

  echo "   Codex: OK"
}

compile_kiro() {
  echo ">> Compilando para Kiro..."

  local steering_dir="$PROJECT_ROOT/.kiro/steering"
  local hooks_dir="$PROJECT_ROOT/.kiro/hooks"
  local skills_dir="$PROJECT_ROOT/.kiro/skills"

  mkdir -p "$steering_dir" "$hooks_dir" "$skills_dir"

  for rule_file in "$HARNESS_DIR"/rules/*.md; do
    [[ -f "$rule_file" ]] || continue
    local basename=$(basename "$rule_file" .md)
    cp "$rule_file" "$steering_dir/$basename.md"
    echo "   [steering] $basename.md"
  done

  for hook_script in "$HARNESS_DIR"/hooks/*.sh; do
    [[ -f "$hook_script" ]] || continue
    local basename=$(basename "$hook_script")
    cp "$hook_script" "$hooks_dir/$basename"
    chmod +x "$hooks_dir/$basename"
    echo "   [hook] $basename"
  done

  for skill_file in "$HARNESS_DIR"/skills/*.md; do
    [[ -f "$skill_file" ]] || continue
    local basename=$(basename "$skill_file" .md)
    local skill_dir="$skills_dir/$basename"
    mkdir -p "$skill_dir"
    cp "$skill_file" "$skill_dir/SKILL.md"
    echo "   [skill] $basename"
  done

  echo "   Kiro: OK"
}

echo "=== Harness Compiler ==="
echo "Fonte: $HARNESS_DIR"
echo "Destino: $PROJECT_ROOT"
echo ""

compiled=0

if is_target_enabled "cursor"; then
  compile_cursor
  compiled=$((compiled + 1))
fi

if is_target_enabled "claude-code"; then
  compile_claude_code
  compiled=$((compiled + 1))
fi

if is_target_enabled "copilot"; then
  compile_copilot
  compiled=$((compiled + 1))
fi

if is_target_enabled "codex"; then
  compile_codex
  compiled=$((compiled + 1))
fi

if is_target_enabled "kiro"; then
  compile_kiro
  compiled=$((compiled + 1))
fi

echo ""
if [[ $compiled -eq 0 ]]; then
  echo "Nenhum target habilitado ou encontrado. Use: $0 [cursor|claude-code|copilot|codex|kiro|all]"
  exit 1
fi

echo "=== $compiled target(s) compilado(s) com sucesso ==="
