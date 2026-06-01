#!/bin/bash
# Hook: beforeShellExecution (Cursor)
# Intercepta comandos de criação de PR e valida se o body segue o template obrigatório.
# Protocolo: recebe JSON via stdin, retorna JSON via stdout.

export LC_ALL=C.UTF-8
export LANG=C.UTF-8

input=$(cat)
command=$(echo "$input" | jq -r '.command // empty')

is_pr_command() {
  [[ "$command" =~ gh[[:space:]]+pr[[:space:]]+create ]] || \
  [[ "$command" =~ create_pull_request ]]
}

if ! is_pr_command; then
  echo '{"permission": "allow"}'
  exit 0
fi

extract_body() {
  local body=""

  if [[ "$command" =~ --body[[:space:]]+\"([^\"]+)\" ]]; then
    body="${BASH_REMATCH[1]}"
  elif [[ "$command" =~ --body[[:space:]]+\'([^\']+)\' ]]; then
    body="${BASH_REMATCH[1]}"
  elif [[ "$command" =~ --body[[:space:]]+\"\$\(cat ]]; then
    body=$(echo "$command" | awk '/<<.*EOF/{found=1; next} /^EOF/{found=0} found{print}')
  fi

  if [[ -z "$body" && "$command" =~ --body ]]; then
    body=$(echo "$command" | sed -n 's/.*--body[[:space:]]*"\{0,1\}//;s/"\{0,1\}[[:space:]]*--.*//;s/"\{0,1\}$//;p' 2>/dev/null)
  fi

  echo "$body"
}

body=$(extract_body)

if [[ -z "$body" ]]; then
  cat <<DENY_JSON
{
  "permission": "deny",
  "user_message": "PR bloqueado: nenhum body foi detectado no comando. Use --body com o template obrigatorio.",
  "agent_message": "O comando gh pr create foi chamado sem --body ou com body vazio. Gere o body seguindo o template da rule pr-template antes de criar o PR."
}
DENY_JSON
  exit 0
fi

missing_sections=()

check_section() {
  local section_name="$1"
  local pattern="$2"

  if ! echo "$body" | grep -qiE "$pattern"; then
    missing_sections+=("$section_name")
  fi
}

check_section "Contexto" "##[[:space:]]*Contexto"
check_section "Objetivo" "##[[:space:]]*Objetivo"
check_section "Criterios de Aceite" "##[[:space:]]*Crit[eé]rios"
check_section "Como Testar Manualmente" "##[[:space:]]*Como Testar"
check_section "Cenarios de Teste" "##[[:space:]]*Cen[aá]rios"

if [[ ${#missing_sections[@]} -gt 0 ]]; then
  missing_list=$(printf ', %s' "${missing_sections[@]}")
  missing_list=${missing_list:2}

  cat <<DENY_JSON
{
  "permission": "deny",
  "user_message": "PR bloqueado: o body nao segue o template obrigatorio. Secoes faltando: ${missing_list}. O template exige: Contexto, Objetivo, Criterios de Aceite, Como Testar Manualmente e Cenarios de Teste.",
  "agent_message": "O PR foi bloqueado porque o body nao contem todas as secoes obrigatorias do template. Secoes faltando: ${missing_list}. Leia a rule pr-template para ver o formato correto e peca ao usuario para completar as informacoes necessarias antes de tentar novamente."
}
DENY_JSON
  exit 0
fi

echo '{"permission": "allow"}'
exit 0
