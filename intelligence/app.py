from __future__ import annotations

from fastapi import FastAPI
from pydantic import BaseModel, Field
from typing import Any

from engine import IntelligenceEngine

app = FastAPI(title="DigiSangam Intelligence", version="3.0.0")
engine = IntelligenceEngine()


class GraphRequest(BaseModel):
    graph: dict[str, Any]


class CopilotRequest(BaseModel):
    question: str = Field(min_length=1, max_length=1000)
    graph: dict[str, Any]


class ConciergeRequest(BaseModel):
    question: str = Field(min_length=1, max_length=1000)
    attendee_id: str = Field(min_length=1, max_length=128)
    graph: dict[str, Any]


@app.get("/health")
def health() -> dict[str, Any]:
    return {"ok": True, "service": "digisangam-intelligence", "version": "3.0.0"}


@app.post("/v1/analyze")
def analyze(request: GraphRequest) -> dict[str, Any]:
    return engine.analyze(request.graph)


@app.post("/v1/copilot")
def copilot(request: CopilotRequest) -> dict[str, Any]:
    analysis = engine.analyze(request.graph)
    return engine.copilot(request.question, request.graph, analysis)


@app.post("/v1/concierge")
def concierge(request: ConciergeRequest) -> dict[str, Any]:
    analysis = engine.analyze(request.graph)
    return engine.concierge(request.question, request.attendee_id, request.graph, analysis)
