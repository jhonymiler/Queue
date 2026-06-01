#!/bin/bash
# Hook: preToolUse (Cursor)
# Intercepta apenas chamadas que criam PR (gh pr create)
# e injeta lembrete do template obrigatório.
# Protocolo: recebe JSON via stdin, retorna JSON via stdout.

input=$(cat)
tool_name=$(echo "$input" | jq -r '.tool_name // empty')
tool_input=$(echo "$input" | jq -r '.input // empty')

is_pr_creation() {
  if [[ "$tool_name" == "Shell" ]]; then
    local cmd=$(echo "$tool_input" | jq -r '.command // empty' 2>/dev/null)
    [[ "$cmd" =~ gh[[:space:]]+pr[[:space:]]+create ]] && return 0
  fi
  return 1
}

if ! is_pr_creation; then
  echo '{"permission": "allow"}'
  exit 0
fi

cat <<RESPONSE_JSON
{
  "permission": "ask",
  "user_message": "Um PR esta sendo criado. Verificando conformidade com o template obrigatorio...",
  "agent_message": "ATENCAO: Antes de criar este PR, verifique se o body contem TODAS as secoes obrigatorias: 1) Contexto, 2) Objetivo, 3) Criterios de Aceite (min. 2 itens), 4) Como Testar Manualmente (min. 2 passos), 5) Cenarios de Teste (min. 2 cenarios). Se alguma secao estiver faltando, NAO execute o comando."
}
RESPONSE_JSON
exit 0
