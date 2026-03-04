import { useState, useCallback, useEffect, useRef } from 'react'
import { generateSessionId } from '../lib/utils'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

export function useChat() {
  const [messages, setMessages] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [sessionId, setSessionId] = useState(null)
  const [error, setError] = useState(null)
  const abortControllerRef = useRef(null)

  // Initialize session on mount
  useEffect(() => {
    const storedSessionId = localStorage.getItem('kwekwe_chat_session_id')
    if (storedSessionId) {
      setSessionId(storedSessionId)
    } else {
      const newSessionId = generateSessionId()
      setSessionId(newSessionId)
      localStorage.setItem('kwekwe_chat_session_id', newSessionId)
    }
  }, [])

  // Handle chat suggestions
  useEffect(() => {
    const handleSuggestion = (event) => {
      sendMessage(event.detail)
    }

    window.addEventListener('chatSuggestion', handleSuggestion)
    return () => window.removeEventListener('chatSuggestion', handleSuggestion)
  }, [])

  const sendMessage = useCallback(async (content) => {
    if (!content.trim() || isLoading) return

    // Cancel any ongoing request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController()

    const userMessage = {
      role: 'user',
      content: content.trim(),
      timestamp: new Date().toISOString(),
      read: false
    }

    setMessages(prev => [...prev, userMessage])
    setIsLoading(true)
    setError(null)

    try {
      const response = await fetch(`${API_BASE_URL}/api/v1/chat/query`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: content.trim(),
          session_id: sessionId,
          use_tools: true
        }),
        signal: abortControllerRef.current.signal
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      const botMessage = {
        role: 'assistant',
        content: data.response,
        timestamp: data.timestamp,
        sources: data.sources || [],
        query_type: data.query_type,
        read: true
      }

      setMessages(prev => [...prev, botMessage])

      // Update session ID if new one was created
      if (data.session_id && data.session_id !== sessionId) {
        setSessionId(data.session_id)
        localStorage.setItem('kwekwe_chat_session_id', data.session_id)
      }

    } catch (err) {
      if (err.name === 'AbortError') {
        console.log('Request was aborted')
        return
      }

      console.error('Chat error:', err)
      setError(err.message)

      const errorMessage = {
        role: 'assistant',
        content: 'Sorry, I encountered an error while processing your request. Please try again later.',
        timestamp: new Date().toISOString(),
        error: true
      }

      setMessages(prev => [...prev, errorMessage])
    } finally {
      setIsLoading(false)
      abortControllerRef.current = null
    }
  }, [sessionId, isLoading])

  const clearMessages = useCallback(() => {
    setMessages([])
    setError(null)
  }, [])

  const retryLastMessage = useCallback(() => {
    if (messages.length > 0) {
      const lastUserMessage = [...messages].reverse().find(msg => msg.role === 'user')
      if (lastUserMessage) {
        sendMessage(lastUserMessage.content)
      }
    }
  }, [messages, sendMessage])

  const markMessagesAsRead = useCallback(() => {
    setMessages(prev => prev.map(msg => 
      msg.role === 'user' ? { ...msg, read: true } : msg
    ))
  }, [])

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort()
      }
    }
  }, [])

  return {
    messages,
    isLoading,
    error,
    sessionId,
    sendMessage,
    clearMessages,
    retryLastMessage,
    markMessagesAsRead
  }
}
